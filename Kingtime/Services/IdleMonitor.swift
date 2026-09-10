import AppKit
import CoreGraphics

/// Watches for the Mac sitting untouched. Once no key or mouse event has
/// come in for `threshold`, the moment the last event happened is
/// remembered; when the next event arrives, `onReturn` gets that moment and
/// how long the stretch lasted. Sleep counts as idle: the lid closing is the
/// last moment anyone was at the keyboard.
@MainActor
final class IdleMonitor {
    var threshold: TimeInterval = 15 * 60

    /// Whether an idle stretch is worth tracking right now (a timer runs).
    var isArmed: () -> Bool = { true }

    var onReturn: ((_ since: Date, _ duration: TimeInterval) -> Void)?

    private var timer: Timer?
    private var idleSince: Date?

    func start() {
        timer = Timer.scheduledTimer(withTimeInterval: 5, repeats: true) { [weak self] _ in
            Task { @MainActor in self?.poll() }
        }

        let center = NSWorkspace.shared.notificationCenter

        center.addObserver(forName: NSWorkspace.willSleepNotification, object: nil, queue: .main) { [weak self] _ in
            Task { @MainActor in self?.systemWillSleep() }
        }

        center.addObserver(forName: NSWorkspace.didWakeNotification, object: nil, queue: .main) { [weak self] _ in
            Task { @MainActor in self?.poll() }
        }
    }

    static var secondsIdle: TimeInterval {
        CGEventSource.secondsSinceLastEventType(.combinedSessionState, eventType: CGEventType(rawValue: ~0)!)
    }

    private func poll() {
        let idle = Self.secondsIdle

        if idleSince == nil {
            if idle >= threshold, isArmed() {
                idleSince = Date().addingTimeInterval(-idle)
            }
        } else if idle < threshold, let since = idleSince {
            idleSince = nil
            let duration = Date().timeIntervalSince(since)

            if duration >= threshold {
                onReturn?(since, duration)
            }
        }
    }

    private func systemWillSleep() {
        guard idleSince == nil, isArmed() else {
            return
        }

        idleSince = Date().addingTimeInterval(-Self.secondsIdle)
    }
}
