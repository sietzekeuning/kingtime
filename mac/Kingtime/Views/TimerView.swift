import Sparkle
import SwiftUI

struct TimerView: View {
    @Bindable var store: TimerStore
    let updater: SPUUpdater
    @Binding var mode: PanelMode
    @Binding var theme: PanelTheme

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            PanelHeader(store: store, updater: updater, mode: $mode, theme: $theme)

            if let timer = store.timer {
                runningCard(timer)
            }

            pickers

            if let message = store.errorMessage {
                Text(message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .fixedSize(horizontal: false, vertical: true)
            }

            primaryButton
        }
        .padding(16)
    }

    // MARK: - Pieces

    private func runningCard(_ timer: DesktopTimer) -> some View {
        HStack(alignment: .center, spacing: 12) {
            Circle()
                .fill(Color(hex: timer.projectColor) ?? .accentColor)
                .frame(width: 10, height: 10)

            VStack(alignment: .leading, spacing: 2) {
                Text(timer.projectName)
                    .font(.subheadline.weight(.semibold))
                    .lineLimit(1)
                Text(timer.notes?.isEmpty == false ? "\(timer.clientName) · \(timer.notes!)" : timer.clientName)
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }

            Spacer()

            Text(store.elapsedText)
                .font(.system(.title3, design: .rounded).weight(.semibold))
                .monospacedDigit()
        }
        .padding(12)
        .panelPane(theme)
    }

    private var pickers: some View {
        VStack(spacing: 8) {
            PopUpPicker(
                placeholder: "Choose a client",
                items: store.clients.map { PopUpPicker.Item(id: $0.id, title: $0.name) },
                selection: $store.selectedClientId
            )

            PopUpPicker(
                placeholder: store.selectedClientId == nil ? "Choose a client first" : "Choose a project",
                items: store.projectsForSelectedClient.map { project in
                    PopUpPicker.Item(id: project.id, title: project.code.map { "\(project.name) (\($0))" } ?? project.name)
                },
                selection: $store.selectedProjectId
            )
            .disabled(store.selectedClientId == nil)

            TextField("Notes (optional)", text: $store.notes)
                .panelField(theme)
                .onSubmit {
                    Task { await store.pressPrimaryButton() }
                }
        }
    }

    private var primaryButton: some View {
        let stopping = store.isRunning && store.selectionMatchesRunningTimer

        return Button {
            Task { await store.pressPrimaryButton() }
        } label: {
            HStack(spacing: 8) {
                if store.isBusy {
                    ProgressView()
                        .controlSize(.small)
                } else {
                    Image(systemName: stopping ? "stop.fill" : "play.fill")
                }
                Text(stopping ? "Stop timer" : (store.isRunning ? "Switch timer" : "Start timer"))
            }
            .frame(maxWidth: .infinity)
        }
        .panelPrimaryButton(theme, tint: stopping ? .red : .accentColor)
        .controlSize(.large)
        .keyboardShortcut(.defaultAction)
        .disabled(store.isBusy || store.selectedProjectId == nil)
    }
}

extension Color {
    /// `#RRGGBB`, as the projects carry their colour.
    init?(hex: String?) {
        guard let hex, hex.hasPrefix("#"), hex.count == 7, let value = UInt32(hex.dropFirst(), radix: 16) else {
            return nil
        }

        self.init(
            red: Double((value >> 16) & 0xFF) / 255,
            green: Double((value >> 8) & 0xFF) / 255,
            blue: Double(value & 0xFF) / 255
        )
    }
}
