import Sparkle
import SwiftUI

struct MenuBarView: View {
    let store: TimerStore
    let week: WeekStore
    let updater: SPUUpdater

    /// What a click on the status item opens: the timer or the week grid.
    /// The gear menu and the switch in the header both write it back.
    @State private var mode = PanelMode.current

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
                if mode == .week {
                    WeekView(store: store, week: week, updater: updater, mode: $mode)
                } else {
                    TimerView(store: store, updater: updater, mode: $mode)
                }
            }
        }
        .frame(width: store.phase == .signedIn ? mode.panelWidth : PanelMode.timer.panelWidth)
        .onAppear {
            mode = PanelMode.current
            store.panelOpened()
        }
    }
}
