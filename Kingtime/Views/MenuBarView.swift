import Sparkle
import SwiftUI

struct MenuBarView: View {
    let store: TimerStore
    let updater: SPUUpdater

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
                TimerView(store: store, updater: updater)
            }
        }
        .frame(width: 320)
        .onAppear {
            store.panelOpened()
        }
    }
}
