import Foundation

/// What the panel under the status item opens on: the day with its entries,
/// or the week grid. Stored in UserDefaults, so the choice outlives a restart.
enum PanelMode: String, CaseIterable, Identifiable {
    case day
    case week

    var id: String { rawValue }

    var title: String {
        switch self {
        case .day: return "Day"
        case .week: return "This week"
        }
    }

    var symbol: String {
        switch self {
        case .day: return "list.bullet"
        case .week: return "calendar"
        }
    }

    /// The panel is as wide as its contents need: the week grid carries
    /// seven days, a project column and two totals.
    var panelWidth: CGFloat {
        switch self {
        case .day: return 380
        case .week: return 640
        }
    }

    /// The sign-in form and the spinner, before there is a mode to show.
    static let signedOutWidth: CGFloat = 320

    private static let key = "panelMode"

    static var current: PanelMode {
        get { PanelMode(rawValue: UserDefaults.standard.string(forKey: key) ?? "") ?? .day }
        set { UserDefaults.standard.set(newValue.rawValue, forKey: key) }
    }
}
