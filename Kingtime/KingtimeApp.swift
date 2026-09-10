import Sparkle
import SwiftUI

@main
struct KingtimeApp: App {
    @NSApplicationDelegateAdaptor(AppDelegate.self) private var appDelegate

    var body: some Scene {
        MenuBarExtra {
            MenuBarView(store: appDelegate.store, updater: appDelegate.updaterController.updater)
        } label: {
            MenuBarLabel(store: appDelegate.store)
        }
        .menuBarExtraStyle(.window)
    }
}

@MainActor
final class AppDelegate: NSObject, NSApplicationDelegate {
    let store = TimerStore()

    /// Sparkle: checks kingtime.nl/download/appcast.xml every six hours,
    /// downloads a newer build in the background and installs it on quit.
    let updaterController = SPUStandardUpdaterController(startingUpdater: true, updaterDelegate: nil, userDriverDelegate: nil)

    private var snapshotWindow: NSWindow?

    func applicationDidFinishLaunching(_ notification: Notification) {
        if ProcessInfo.processInfo.environment["KINGTIME_SIGNED_OUT"] != nil {
            Keychain.delete("token")
        }

        store.start()

        if let path = ProcessInfo.processInfo.environment["KINGTIME_SNAPSHOT"] {
            if let appearance = ProcessInfo.processInfo.environment["KINGTIME_APPEARANCE"] {
                NSApp.appearance = NSAppearance(named: appearance == "light" ? .aqua : .darkAqua)
            }

            snapshotPanel(to: path)
        }
    }

    /// Development aid: `KINGTIME_SNAPSHOT=/tmp/panel.png Kingtime` renders
    /// the panel into a window, writes it to that path and quits. Screen
    /// recording permission is not needed, which a screenshot would be.
    private func snapshotPanel(to path: String) {
        let view = NSHostingView(rootView: MenuBarView(store: store, updater: updaterController.updater))
        let window = NSWindow(contentRect: NSRect(x: 0, y: 0, width: 320, height: 10), styleMask: [.titled], backing: .buffered, defer: false)
        window.contentView = view
        window.setContentSize(view.fittingSize)
        window.center()
        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
        snapshotWindow = window

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
