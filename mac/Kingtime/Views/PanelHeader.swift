import Sparkle
import SwiftUI

/// The top of the panel, the same above the timer and above the week: the
/// mark, a switch between the two views and the gear menu.
struct PanelHeader: View {
    let store: TimerStore
    let updater: SPUUpdater
    @Binding var mode: PanelMode
    @Binding var theme: PanelTheme

    @State private var launchAtLogin = LaunchAtLogin.isEnabled
    @State private var appearance = AppearanceSetting.current

    var body: some View {
        HStack(spacing: 8) {
            Image("PanelIcon")
                .resizable()
                .frame(width: 22, height: 22)
            Text("Kingtime")
                .font(.headline)

            Spacer()

            modeSwitch
            gearMenu
        }
    }

    /// Clicking a side switches the panel and remembers it, so the status
    /// item opens on whatever was last used.
    private var modeSwitch: some View {
        Picker("", selection: $mode) {
            ForEach(PanelMode.allCases) { option in
                Image(systemName: option.symbol)
                    .help(option.title)
                    .tag(option)
            }
        }
        .pickerStyle(.segmented)
        .labelsHidden()
        .fixedSize()
        .onChange(of: mode) { _, selected in
            PanelMode.current = selected
        }
    }

    private var gearMenu: some View {
        Menu {
            Button("Open Kingtime in the browser") {
                NSWorkspace.shared.open(KingtimeClient.baseURL.appendingPathComponent("time-entries"))
            }
            Button("Refresh") {
                Task { await store.refresh() }
            }
            Divider()
            Picker(selection: $mode) {
                ForEach(PanelMode.allCases) { option in
                    Label(option.title, systemImage: option.symbol).tag(option)
                }
            } label: {
                Label("Clicking the icon shows", systemImage: mode.symbol)
            }
            Toggle("Launch at login", isOn: $launchAtLogin)
                .onChange(of: launchAtLogin) { _, enabled in
                    try? LaunchAtLogin.set(enabled)
                    launchAtLogin = LaunchAtLogin.isEnabled
                }
            Picker(selection: $appearance) {
                ForEach(AppearanceSetting.allCases) { setting in
                    Label(setting.title, systemImage: setting.symbol).tag(setting)
                }
            } label: {
                Label("Appearance", systemImage: appearance.symbol)
            }
            .onChange(of: appearance) { _, setting in
                AppearanceSetting.current = setting
            }
            Picker(selection: $theme) {
                ForEach(PanelTheme.allCases) { option in
                    Label(option.title, systemImage: option.symbol).tag(option)
                }
            } label: {
                Label("Panel", systemImage: theme.symbol)
            }
            .onChange(of: theme) { _, selected in
                PanelTheme.current = selected
            }
            Text(theme.note)
            Button("Check for updates…") {
                updater.checkForUpdates()
            }
            .disabled(!updater.canCheckForUpdates)
            Divider()
            if let user = store.user {
                Text("Signed in as \(user.email)")
            }
            Button("Sign out") {
                Task { await store.signOut() }
            }
            Divider()
            Text("Version \(Bundle.main.shortVersion)")
            Button("Quit Kingtime") {
                NSApp.terminate(nil)
            }
            .keyboardShortcut("q")
        } label: {
            Image(systemName: "gearshape")
        }
        .menuStyle(.borderlessButton)
        .menuIndicator(.hidden)
        .fixedSize()
    }
}
