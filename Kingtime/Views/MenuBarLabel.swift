import SwiftUI

/// The status item: the crown clock, with the running time next to it.
struct MenuBarLabel: View {
    let store: TimerStore

    var body: some View {
        HStack(spacing: 4) {
            Image("MenuBarIcon")

            if let text = store.menuBarText {
                Text(text)
                    .monospacedDigit()
            }
        }
    }
}
