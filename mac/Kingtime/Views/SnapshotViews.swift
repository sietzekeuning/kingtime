import Sparkle
import SwiftUI

/// Compositions rendered by the KINGTIME_SNAPSHOT modes for kingtime.nl:
/// nothing here is shown in the app itself.

/// The panel as it hangs under the menu bar: a strip with the status item
/// (our template glyph plus the running time) and the panel beneath it.
struct HeroSnapshotView: View {
    let store: TimerStore
    let updater: Sparkle.SPUUpdater

    var body: some View {
        VStack(spacing: 0) {
            HStack(spacing: 18) {
                Image(systemName: "apple.logo")
                Text("Finder").fontWeight(.semibold)
                Text("File")
                Text("Edit")
                Text("View")
                Text("Go")
                Spacer()
                HStack(spacing: 5) {
                    Image("MenuBarIcon")
                    Text("1:23").monospacedDigit()
                }
                .padding(.horizontal, 7)
                .padding(.vertical, 3)
                .background(Color.white.opacity(0.18), in: RoundedRectangle(cornerRadius: 5))
                Text("Thu 10 Sep  14:02").monospacedDigit()
            }
            .font(.system(size: 13))
            .foregroundStyle(.white.opacity(0.92))
            .padding(.horizontal, 14)
            .frame(height: 28)
            .frame(maxWidth: .infinity)
            .background(Color(red: 0.16, green: 0.16, blue: 0.17))

            HStack {
                Spacer()
                MenuBarView(store: store, updater: updater)
                    .background(Color(nsColor: .windowBackgroundColor))
                    .clipShape(RoundedRectangle(cornerRadius: 12))
                    .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(Color.black.opacity(0.12)))
                    .shadow(color: .black.opacity(0.25), radius: 22, y: 12)
                    .padding(.top, 6)
                    .padding(.trailing, 118)
            }
            .padding(.bottom, 32)
        }
        .frame(width: 720)
    }
}

/// The "you were away" alert, drawn as a view so it can be rendered.
struct IdlePromptSnapshotView: View {
    var body: some View {
        VStack(spacing: 14) {
            Image("PanelIcon")
                .resizable()
                .frame(width: 64, height: 64)
            VStack(spacing: 6) {
                Text("You were away for 23 min")
                    .font(.system(size: 13, weight: .bold))
                Text("Your Mac has been idle since 13:39 while the timer on Website redesign · Acme kept running. Take that time off the entry?")
                    .font(.system(size: 11))
                    .multilineTextAlignment(.center)
                    .fixedSize(horizontal: false, vertical: true)
            }
            VStack(spacing: 6) {
                Button("Deduct 23 min") {}
                    .buttonStyle(.borderedProminent)
                    .frame(maxWidth: .infinity)
                Button("Deduct and stop timer") {}
                    .frame(maxWidth: .infinity)
                Button("Keep the time") {}
                    .frame(maxWidth: .infinity)
            }
            .controlSize(.large)
        }
        .padding(20)
        .frame(width: 260)
        .background(Color(nsColor: .windowBackgroundColor))
        .clipShape(RoundedRectangle(cornerRadius: 12))
        .overlay(RoundedRectangle(cornerRadius: 12).strokeBorder(Color.black.opacity(0.12)))
        .shadow(color: .black.opacity(0.25), radius: 22, y: 12)
        .padding(32)
    }
}
