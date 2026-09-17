import Foundation
import Observation

/// The day view in the panel: the week strip with a total per day, and the
/// entries of the chosen day. It reads the same week payload as the grid
/// (asked around the chosen day), and every add, change or delete answers
/// with that payload again, so totals are never a guess.
@MainActor
@Observable
final class DayStore {
    private(set) var sheet: WeekSheet?
    private(set) var isLoading = false
    private(set) var isBusy = false
    var errorMessage: String?

    /// The day on show; nil is today.
    private(set) var selectedDate: String?

    /// When the sheet came in. A running timer keeps counting after that,
    /// so the totals of its day add the time since.
    private(set) var loadedAt = Date()

    private var client = KingtimeClient()
    private var isDemo = false

    // MARK: - Reading

    var entries: [DayEntry] {
        sheet?.entries ?? []
    }

    var selectedDay: WeekDay? {
        sheet?.days.first { $0.date == sheet?.selectedDate }
    }

    var isToday: Bool {
        selectedDay?.isToday ?? true
    }

    func isToday(_ date: String) -> Bool {
        sheet?.days.first { $0.date == date }?.isToday ?? false
    }

    // MARK: - Loading

    func opened() async {
        await load(around: selectedDate, quietly: sheet != nil)
    }

    func reload() async {
        guard sheet != nil else {
            return
        }

        await load(around: selectedDate, quietly: true)
    }

    func select(_ date: String) async {
        await load(around: date)
    }

    func showPreviousWeek() async {
        await load(around: WeekDates.add(days: -7, to: sheet?.selectedDate ?? today))
    }

    func showNextWeek() async {
        await load(around: WeekDates.add(days: 7, to: sheet?.selectedDate ?? today))
    }

    func showToday() async {
        await load(around: nil)
    }

    private func load(around date: String?, quietly: Bool = false) async {
        guard !isDemo else {
            return
        }

        selectedDate = date

        guard ensureToken() else {
            return
        }

        isLoading = true
        defer { isLoading = false }

        do {
            apply(try await client.week(around: date))

            if !quietly {
                errorMessage = nil
            }
        } catch {
            report(error, quietly: quietly)
        }
    }

    // MARK: - Writing

    /// Adds an entry and answers with its id, so "save and start" can put
    /// the timer on it straight away.
    func add(projectId: Int, date: String, hours: Double, notes: String) async -> Int? {
        let before = Set(entries.map(\.id))

        guard await perform({ try await self.client.addEntry(projectId: projectId, date: date, hours: hours, notes: notes) }) else {
            return nil
        }

        return entries.map(\.id).filter { !before.contains($0) }.max()
    }

    func update(_ entry: DayEntry, projectId: Int, hours: Double, notes: String) async -> Bool {
        await perform { try await self.client.updateEntry(id: entry.id, projectId: projectId, hours: hours, notes: notes) }
    }

    func delete(_ entry: DayEntry) async -> Bool {
        await perform { try await self.client.deleteEntry(id: entry.id) }
    }

    private func perform(_ call: () async throws -> WeekSheet) async -> Bool {
        guard !isDemo, ensureToken() else {
            return false
        }

        errorMessage = nil
        isBusy = true
        defer { isBusy = false }

        do {
            apply(try await call())

            return true
        } catch {
            report(error, quietly: false)

            return false
        }
    }

    private func apply(_ sheet: WeekSheet) {
        self.sheet = sheet
        loadedAt = Date()
    }

    private func report(_ error: Error, quietly: Bool) {
        if case KingtimeError.unauthorized = error {
            client.token = nil
        }

        if !quietly {
            errorMessage = error.localizedDescription
        }
    }

    // MARK: - Session

    @discardableResult
    private func ensureToken() -> Bool {
        if client.token == nil {
            client.token = Keychain.read("token")
        }

        return client.token != nil
    }

    func forgetSession() {
        client.token = nil
        sheet = nil
        selectedDate = nil
        errorMessage = nil
    }

    private var today: String {
        ISO8601DateFormatter.string(from: Date(), timeZone: .current, formatOptions: [.withFullDate])
    }

    /// The made-up week of the screenshots, on its Thursday.
    func loadDemo() {
        isDemo = true
        sheet = WeekStore.demoSheet()
    }
}
