import Sparkle
import SwiftUI

struct MenuBarView: View {
    let store: TimerStore
    let week: WeekStore
    let day: DayStore
    let updater: SPUUpdater

    /// What a click on the status item opens: the day, the timer or the week.
    /// The gear menu and the switch in the header both write it back.
    @State private var mode = PanelMode.current

    /// Classic panes or glass. Read once, so switching it redraws at once.
    @State private var theme = PanelTheme.current

    var body: some View {
        Group {
            switch store.phase {
            case .loading:
                ProgressView()
                    .frame(maxWidth: .infinity)
                    .padding(40)
            case .signedOut:
                LoginView(store: store)
            case .signedIn:
                switch mode {
                case .day:
                    DayView(store: store, day: day, updater: updater, mode: $mode, theme: $theme)
                case .timer:
                    TimerView(store: store, updater: updater, mode: $mode, theme: $theme)
                case .week:
                    WeekView(store: store, week: week, updater: updater, mode: $mode, theme: $theme)
                }
            }
        }
        .environment(\.panelTheme, theme)
        .panelGlass(theme)
        .frame(width: store.phase == .signedIn ? mode.panelWidth : PanelMode.timer.panelWidth)
        .onAppear {
            mode = PanelMode.current
            theme = PanelTheme.current
            store.panelOpened()
        }
    }
}
