import Foundation

/// What the panel under the status item opens on: the running timer, or the
/// week grid. Stored in UserDefaults, so the choice outlives a restart.
enum PanelMode: String, CaseIterable, Identifiable {
    case timer
    case week

    var id: String { rawValue }

    var title: String {
        switch self {
        case .timer: return "Timer"
        case .week: return "This week"
        }
    }

    var symbol: String {
        switch self {
        case .timer: return "stopwatch"
        case .week: return "calendar"
        }
    }

    /// The panel is as wide as its contents need: the week grid carries
    /// seven days, a project column and two totals.
    var panelWidth: CGFloat {
        switch self {
        case .timer: return 320
        case .week: return 640
        }
    }

    private static let key = "panelMode"

    static var current: PanelMode {
        get { PanelMode(rawValue: UserDefaults.standard.string(forKey: key) ?? "") ?? .timer }
        set { UserDefaults.standard.set(newValue.rawValue, forKey: key) }
    }
}
