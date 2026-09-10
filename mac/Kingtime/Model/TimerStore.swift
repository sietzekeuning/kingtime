import AppKit
import Foundation
import Observation

/// Everything the menu bar shows and does: the signed-in account, the
/// projects to pick from, the running timer and its clock. It polls the
/// server, so a timer started on the web or through MCP shows up here too.
@MainActor
@Observable
final class TimerStore {
    enum Phase {
        case loading
        case signedOut
        case signedIn
    }

    static let idleThreshold: TimeInterval = 15 * 60

    private(set) var phase: Phase = .loading {
        didSet {
            if phase != oldValue {
                onPhaseChange?(phase)
            }
        }
    }

    /// Hooks for the app delegate: the panel opens itself the first time
    /// there is no account, and a fresh sign-in offers launch at login.
    var onPhaseChange: ((Phase) -> Void)?
    var onSignedIn: (() -> Void)?

    /// Screenshot aid: behave as if no token were stored.
    var forceSignedOut = false

    /// Screenshot aid: made-up data, no server (see loadDemo).
    private var isDemo = false
    private(set) var user: DesktopUser?
    private(set) var projects: [ProjectOption] = []
    private(set) var timer: DesktopTimer?
    private(set) var isBusy = false
    var errorMessage: String?

    /// Whether the server asked for a two-factor code on the last sign-in.
    private(set) var needsTwoFactorCode = false

    var selectedClientId: Int? {
        didSet {
            if selectedProject?.clientId != selectedClientId {
                selectedProjectId = projectsForSelectedClient.count == 1 ? projectsForSelectedClient.first?.id : nil
            }
        }
    }

    var selectedProjectId: Int? {
        didSet {
            UserDefaults.standard.set(selectedProjectId ?? 0, forKey: "lastProjectId")
        }
    }

    var notes = ""

    /// Ticks once a second while a timer runs, so the clock re-renders.
    private(set) var now = Date()

    /// Server clock minus ours, so the elapsed time matches the web app
    /// even on a Mac whose clock drifts.
    private var clockOffset: TimeInterval = 0

    private var client = KingtimeClient()
    private var tickTimer: Timer?
    private var pollTimer: Timer?
    private let idleMonitor = IdleMonitor()

    // MARK: - Derived

    var clients: [(id: Int, name: String)] {
        var seen: Set<Int> = []

        return projects.compactMap { project in
            guard seen.insert(project.clientId).inserted else {
                return nil
            }

            return (id: project.clientId, name: project.clientName)
        }
        .sorted { $0.name.localizedCaseInsensitiveCompare($1.name) == .orderedAscending }
    }

    var projectsForSelectedClient: [ProjectOption] {
        guard let selectedClientId else {
            return []
        }

        return projects.filter { $0.clientId == selectedClientId }
    }

    var selectedProject: ProjectOption? {
        projects.first { $0.id == selectedProjectId }
    }

    var isRunning: Bool {
        timer != nil
    }

    /// The pickers describe the timer that runs, so the button means "stop".
    /// Anything else, and pressing it starts (or switches to) that project.
    var selectionMatchesRunningTimer: Bool {
        guard let timer else {
            return false
        }

        return timer.projectId == selectedProjectId && (timer.notes ?? "") == notes.trimmingCharacters(in: .whitespacesAndNewlines)
    }

    var elapsedSeconds: Int {
        timer?.elapsedSeconds(at: now.addingTimeInterval(clockOffset)) ?? 0
    }

    var elapsedText: String {
        Self.format(seconds: elapsedSeconds)
    }

    var menuBarText: String? {
        guard isRunning else {
            return nil
        }

        let seconds = elapsedSeconds

        return String(format: "%d:%02d", seconds / 3600, (seconds % 3600) / 60)
    }

    static func format(seconds: Int) -> String {
        String(format: "%d:%02d:%02d", seconds / 3600, (seconds % 3600) / 60, seconds % 60)
    }

    // MARK: - Lifecycle

    func start() {
        if ProcessInfo.processInfo.environment["KINGTIME_DEMO"] != nil {
            loadDemo()
            return
        }

        client.token = forceSignedOut ? nil : Keychain.read("token")

        idleMonitor.threshold = Self.idleThreshold
        idleMonitor.isArmed = { [weak self] in self?.isRunning ?? false }
        idleMonitor.onReturn = { [weak self] since, duration in
            self?.handleIdleReturn(since: since, duration: duration)
        }
        idleMonitor.start()

        pollTimer = Timer.scheduledTimer(withTimeInterval: 30, repeats: true) { [weak self] _ in
            Task { @MainActor in await self?.refresh(quietly: true) }
        }

        Task {
            await refresh()
        }
    }

    /// Called when the panel opens, so it never shows stale state.
    func panelOpened() {
        Task {
            await refresh(quietly: true)
        }
    }

    /// Fixed, made-up data for the screenshots on kingtime.nl, so they show
    /// no real client and need neither a token nor a server.
    private func loadDemo() {
        isDemo = true

        let projects = [
            ProjectOption(id: 1, name: "Website redesign", code: "ACME-1", color: "#F2622A", clientId: 1, clientName: "Acme", isBillable: true),
            ProjectOption(id: 2, name: "Support", code: nil, color: "#3B82F6", clientId: 1, clientName: "Acme", isBillable: true),
            ProjectOption(id: 3, name: "Brand identity", code: nil, color: "#10B981", clientId: 2, clientName: "Globex", isBillable: true),
        ]

        apply(DesktopState(
            user: DesktopUser(id: 1, name: "Sietze", email: "sietze@example.com"),
            projects: projects,
            timer: DesktopTimer(
                id: 1, projectId: 1, projectName: "Website redesign", projectColor: "#F2622A",
                clientId: 1, clientName: "Acme", spentOn: "2026-09-10", notes: "Homepage hero",
                secondsBeforeTimer: 0, timerStartedAt: Date().addingTimeInterval(-(1 * 3600 + 23 * 60 + 45))
            ),
            serverTime: Date()
        ))
        phase = .signedIn
    }

    // MARK: - Session

    func signIn(email: String, password: String, code: String) async {
        errorMessage = nil
        isBusy = true
        defer { isBusy = false }

        do {
            let response = try await client.signIn(
                email: email.trimmingCharacters(in: .whitespaces),
                password: password,
                code: code.isEmpty ? nil : code,
                deviceName: Host.current().localizedName ?? "Mac"
            )

            Keychain.write(response.token, account: "token")
            client.token = response.token
            needsTwoFactorCode = false
            await refresh()
            onSignedIn?()
        } catch let error as KingtimeError {
            if error.message(for: "code") != nil {
                needsTwoFactorCode = true
            }

            errorMessage = error.message(for: "email") ?? error.message(for: "code") ?? error.errorDescription
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func signOut() async {
        isBusy = true
        defer { isBusy = false }

        try? await client.signOut()
        forgetSession()
    }

    private func forgetSession() {
        Keychain.delete("token")
        client.token = nil
        user = nil
        projects = []
        timer = nil
        needsTwoFactorCode = false
        phase = .signedOut
        stopTicking()
    }

    // MARK: - Timer

    func refresh(quietly: Bool = false) async {
        guard !isDemo else {
            return
        }

        guard client.token != nil else {
            phase = .signedOut
            return
        }

        do {
            apply(try await client.state())
            phase = .signedIn
        } catch KingtimeError.unauthorized {
            forgetSession()
        } catch {
            if !quietly {
                errorMessage = error.localizedDescription
            }

            if phase == .loading {
                phase = .signedIn
            }
        }
    }

    func pressPrimaryButton() async {
        if isRunning, selectionMatchesRunningTimer {
            await stopTimer()
        } else {
            await startTimer()
        }
    }

    func startTimer() async {
        guard let selectedProjectId else {
            return
        }

        await perform {
            try await client.startTimer(projectId: selectedProjectId, notes: notes.trimmingCharacters(in: .whitespacesAndNewlines))
        }
    }

    func stopTimer() async {
        await perform {
            try await client.stopTimer()
        }
    }

    func deductIdle(seconds: Int, stop: Bool) async {
        guard let timer else {
            return
        }

        await perform {
            try await client.deductIdle(entryId: timer.id, seconds: seconds, stop: stop)
        }
    }

    private func perform(_ call: () async throws -> DesktopState) async {
        errorMessage = nil
        isBusy = true
        defer { isBusy = false }

        do {
            apply(try await call())
        } catch KingtimeError.unauthorized {
            forgetSession()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func apply(_ state: DesktopState) {
        let previousTimerId = timer?.id

        clockOffset = state.serverTime.timeIntervalSinceNow
        user = state.user
        projects = state.projects
        timer = state.timer
        now = Date()

        if let timer = state.timer {
            // A timer that was started elsewhere (or just here) becomes the
            // selection, but edits in progress on the same timer are kept.
            if timer.id != previousTimerId {
                selectedClientId = timer.clientId
                selectedProjectId = timer.projectId
                notes = timer.notes ?? ""
            }

            startTicking()
        } else {
            if previousTimerId != nil {
                notes = ""
            }

            stopTicking()
        }

        if selectedProjectId == nil, previousTimerId == nil {
            restoreLastProject()
        }
    }

    private func restoreLastProject() {
        let lastId = UserDefaults.standard.integer(forKey: "lastProjectId")

        if let project = projects.first(where: { $0.id == lastId }) {
            selectedClientId = project.clientId
            selectedProjectId = project.id
        } else if selectedClientId == nil, clients.count == 1 {
            selectedClientId = clients.first?.id
        }
    }

    private func startTicking() {
        guard tickTimer == nil else {
            return
        }

        tickTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { [weak self] _ in
            Task { @MainActor in self?.now = Date() }
        }
    }

    private func stopTicking() {
        tickTimer?.invalidate()
        tickTimer = nil
    }

    // MARK: - Idle

    private func handleIdleReturn(since: Date, duration: TimeInterval) {
        guard let timer else {
            return
        }

        let seconds = min(Int(duration), timer.elapsedSeconds(at: Date().addingTimeInterval(clockOffset)))

        guard seconds > 0 else {
            return
        }

        switch IdlePrompt.present(since: since, seconds: seconds, timer: timer) {
        case .keep:
            return
        case .deduct:
            Task { await deductIdle(seconds: seconds, stop: false) }
        case .deductAndStop:
            Task { await deductIdle(seconds: seconds, stop: true) }
        }
    }
}
