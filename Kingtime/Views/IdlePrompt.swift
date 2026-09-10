import AppKit

/// "You were away for 23 minutes." Shown the moment the Mac is used again
/// after sitting idle while a timer ran.
@MainActor
enum IdlePrompt {
    enum Choice {
        case deduct
        case deductAndStop
        case keep
    }

    static func present(since: Date, seconds: Int, timer: DesktopTimer) -> Choice {
        let duration = describe(seconds: seconds)
        let time = since.formatted(date: .omitted, time: .shortened)

        let alert = NSAlert()
        alert.messageText = "You were away for \(duration)"
        alert.informativeText = "Your Mac has been idle since \(time) while the timer on \(timer.projectName) · \(timer.clientName) kept running. Take that time off the entry?"
        alert.alertStyle = .informational
        alert.icon = NSApp.applicationIconImage
        alert.addButton(withTitle: "Deduct \(duration)")
        alert.addButton(withTitle: "Deduct and stop timer")
        alert.addButton(withTitle: "Keep the time")

        NSApp.activate(ignoringOtherApps: true)

        switch alert.runModal() {
        case .alertFirstButtonReturn:
            return .deduct
        case .alertSecondButtonReturn:
            return .deductAndStop
        default:
            return .keep
        }
    }

    static func describe(seconds: Int) -> String {
        let minutes = Int((Double(seconds) / 60).rounded())

        if minutes < 60 {
            return "\(minutes) min"
        }

        let hours = minutes / 60
        let rest = minutes % 60

        return rest == 0 ? "\(hours) h" : "\(hours) h \(rest) min"
    }
}
