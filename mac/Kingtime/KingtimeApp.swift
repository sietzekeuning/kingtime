import Sparkle
import SwiftUI

@main
struct KingtimeApp: App {
    @NSApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate

    var body: some Scene {
        // The app lives in the status bar (see AppDelegate); it has no
        // windows of its own. A Settings scene is the smallest scene there is.
        Settings {
            EmptyView()
        }
    }
}

/// Owns the status item and the panel under it. Plain AppKit rather than
/// SwiftUI's MenuBarExtra, so the panel can be opened from code: on the
/// first launch it shows the sign-in form by itself, and double-clicking
/// the app in Finder while it runs brings the panel up instead of nothing.
@MainActor
final class AppDelegate: NSObject, NSApplicationDelegate {
    let store = TimerStore()

    /// Sparkle: checks kingtime.nl/download/appcast.xml every six hours,
    /// downloads a newer build in the background and installs it on quit.
    let updaterController = SPUStandardUpdaterController(startingUpdater: true, updaterDelegate: nil, userDriverDelegate: nil)

    private var statusItem: NSStatusItem!
    private let popover = NSPopover()
    private var titleTimer: Timer?
    private var snapshotWindow: NSWindow?

    func applicationDidFinishLaunching(_ notification: Notification) {
        AppearanceSetting.current.apply()

        // Screenshot aids. KINGTIME_SIGNED_OUT shows the sign-in form without
        // touching the keychain; KINGTIME_DEMO shows a made-up running timer.
        if ProcessInfo.processInfo.environment["KINGTIME_SIGNED_OUT"] != nil {
            store.forceSignedOut = true
        }

        installStatusItem()

        store.onPhaseChange = { [weak self] phase in
            self?.phaseChanged(phase)
        }
        store.onSignedIn = { [weak self] in
            self?.offerLaunchAtLogin()
        }
        store.start()

        if let path = ProcessInfo.processInfo.environment["KINGTIME_SNAPSHOT"] {
            if let appearance = ProcessInfo.processInfo.environment["KINGTIME_APPEARANCE"] {
                NSApp.appearance = NSAppearance(named: appearance == "light" ? .aqua : .darkAqua)
            }

            snapshotPanel(to: path)
        }

        if ProcessInfo.processInfo.environment["KINGTIME_DEBUG"] != nil {
            DispatchQueue.main.asyncAfter(deadline: .now() + 1) { [self] in
                let frame = statusItem.button?.window?.frame ?? .zero
                FileHandle.standardError.write(Data("status item frame: \(frame) visible: \(statusItem.isVisible) notes: \(store.notes) project: \(String(describing: store.selectedProjectId))\n".utf8))
            }
        }
    }

    /// Finder double-click (or `open -a Kingtime`) while it already runs.
    func applicationShouldHandleReopen(_ sender: NSApplication, hasVisibleWindows flag: Bool) -> Bool {
        showPanel()

        return false
    }

    // MARK: - Status item

    private func installStatusItem() {
        // A new status item lands at the far left of the third-party items,
        // which on a MacBook is under the notch. macOS keeps the position
        // per autosave name in UserDefaults, measured from the right edge;
        // seeding a small value once puts the item next to the system
        // icons. Dragging it (with the command key) still wins afterwards.
        let positionKey = "NSStatusItem Preferred Position Kingtime"
        if !UserDefaults.standard.bool(forKey: "seededStatusItemPosition") {
            UserDefaults.standard.set(1, forKey: positionKey)
            UserDefaults.standard.set(true, forKey: "seededStatusItemPosition")
        }

        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        statusItem.autosaveName = "Kingtime"

        if let button = statusItem.button {
            let image = NSImage(named: "MenuBarIcon")
            image?.isTemplate = true
            button.image = image
            button.imagePosition = .imageLeading
            button.font = NSFont.monospacedDigitSystemFont(ofSize: NSFont.systemFontSize, weight: .regular)
            button.target = self
            button.action = #selector(togglePanel)
            button.toolTip = "Kingtime"
        }

        popover.behavior = .transient
        popover.animates = false

        let hosting = NSHostingController(rootView: MenuBarView(store: store, updater: updaterController.updater))
        hosting.sizingOptions = [.preferredContentSize]
        popover.contentViewController = hosting

        titleTimer = Timer.scheduledTimer(withTimeInterval: 1, repeats: true) { [weak self] _ in
            Task { @MainActor in self?.refreshTitle() }
        }
        refreshTitle()
    }

    private func refreshTitle() {
        guard let button = statusItem.button else {
            return
        }

        let text = store.menuBarText.map { " " + $0 } ?? ""

        if button.title != text {
            button.title = text
        }
    }

    @objc private func togglePanel() {
        if popover.isShown {
            popover.performClose(nil)
        } else {
            showPanel()
        }
    }

    private func showPanel() {
        guard let button = statusItem.button, !popover.isShown else {
            return
        }

        NSApp.activate(ignoringOtherApps: true)
        popover.show(relativeTo: button.bounds, of: button, preferredEdge: .minY)
        popover.contentViewController?.view.window?.makeKey()
    }

    // MARK: - First run

    private var shownSignInPanel = false

    private func phaseChanged(_ phase: TimerStore.Phase) {
        // The first time the app finds no account, it opens the sign-in
        // form itself; a fresh install should not leave people hunting for
        // an icon they have never seen.
        if phase == .signedOut, !shownSignInPanel {
            shownSignInPanel = true
            showPanel()
        }
    }

    private func offerLaunchAtLogin() {
        let key = "offeredLaunchAtLogin"

        guard !UserDefaults.standard.bool(forKey: key), !LaunchAtLogin.isEnabled else {
            return
        }

        UserDefaults.standard.set(true, forKey: key)

        let alert = NSAlert()
        alert.messageText = "Start Kingtime when you log in?"
        alert.informativeText = "Kingtime lives in the menu bar, next to the clock. Starting it with your Mac means the timer is always one click away. You can change this later under the gear menu."
        alert.alertStyle = .informational
        alert.icon = NSApp.applicationIconImage
        alert.addButton(withTitle: "Start at login")
        alert.addButton(withTitle: "Not now")

        NSApp.activate(ignoringOtherApps: true)

        if alert.runModal() == .alertFirstButtonReturn {
            try? LaunchAtLogin.set(true)
        }
    }

    // MARK: - Snapshot

    /// Development aid: `KINGTIME_SNAPSHOT=/tmp/panel.png Kingtime` renders
    /// the panel into a window, writes it to that path and quits. Screen
    /// recording permission is not needed, which a screenshot would be.
    private func snapshotPanel(to path: String) {
        // The window is shown but never made key: a key window would take
        // the keyboard away from whoever is typing elsewhere, and select
        // the first text field. Controls are told to draw as active instead.
        let kind = ProcessInfo.processInfo.environment["KINGTIME_SNAPSHOT_KIND"] ?? "panel"
        let root = Group {
            switch kind {
            case "hero":
                HeroSnapshotView(store: store, updater: updaterController.updater)
            case "idle":
                IdlePromptSnapshotView()
            default:
                MenuBarView(store: store, updater: updaterController.updater)
            }
        }
        .environment(\.controlActiveState, .key)
        let view = NSHostingView(rootView: root)
        let window = SnapshotWindow(contentRect: NSRect(x: 0, y: 0, width: 320, height: 10), styleMask: [.titled], backing: .buffered, defer: false)
        window.contentView = view
        window.setContentSize(view.fittingSize)
        window.center()
        window.orderFront(nil)
        snapshotWindow = window

        // AppKit still hands the first text field a field editor; drop it,
        // or the notes show up with a grey selection.
        DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
            window.makeFirstResponder(nil)
        }

        DispatchQueue.main.asyncAfter(deadline: .now() + 3) {
            window.setContentSize(view.fittingSize)
            view.layoutSubtreeIfNeeded()

            guard let rep = view.bitmapImageRepForCachingDisplay(in: view.bounds) else {
                NSApp.terminate(nil)
                return
            }

            view.cacheDisplay(in: view.bounds, to: rep)
            try? rep.representation(using: .png, properties: [:])?.write(to: URL(fileURLWithPath: path))
            NSApp.terminate(nil)
        }
    }
}

/// A window that can never take the keyboard, so rendering a snapshot
/// does not swallow whatever someone is typing in another app.
private final class SnapshotWindow: NSWindow {
    override var canBecomeKey: Bool { false }
    override var canBecomeMain: Bool { false }
}
