import Foundation

/// How the panel is dressed. Classic is the solid look Kingtime has always
/// had; glass hands the panes to the system, so the panel is made of the
/// same material as the rest of the Mac it hangs on. Stored in
/// UserDefaults, next to the appearance.
enum PanelTheme: String, CaseIterable, Identifiable {
    case classic
    case glass

    var id: String { rawValue }

    var title: String {
        switch self {
        case .classic: return "Classic"
        case .glass: return "Glass"
        }
    }

    var symbol: String {
        switch self {
        case .classic: return "square.fill"
        case .glass: return "cube.transparent"
        }
    }

    /// What the choice does, in one line.
    var note: String {
        switch self {
        case .classic:
            return "Solid panes, the way Kingtime has always looked."
        case .glass:
            if #available(macOS 26, *) {
                return "Liquid Glass, like the rest of your Mac."
            }

            return "Translucent panes that let the panel through behind them."
        }
    }

    private static let key = "panelTheme"

    static var current: PanelTheme {
        get { PanelTheme(rawValue: UserDefaults.standard.string(forKey: key) ?? "") ?? .classic }
        set { UserDefaults.standard.set(newValue.rawValue, forKey: key) }
    }
}
