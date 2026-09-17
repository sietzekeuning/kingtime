import Sparkle
import SwiftUI

/// The day in the panel, the way Harvest shows it: a strip with the seven
/// days of the week and their totals, the entries of the chosen day, and a
/// play button on each of today's entries. Type in half an hour, press play,
/// and the timer carries on from 0:30.
struct DayView: View {
    let store: TimerStore
    let day: DayStore
    let updater: SPUUpdater
    @Binding var mode: PanelMode
    @Binding var theme: PanelTheme

    /// The entry being added or changed; nil shows the list.
    @State private var draft: EntryDraft?

    var body: some View {
        VStack(alignment: .leading, spacing: 12) {
            PanelHeader(store: store, updater: updater, mode: $mode, theme: $theme)

            if let draft {
                EntryEditor(store: store, day: day, draft: draft) {
                    self.draft = nil
                }
            } else {
                strip
                title

                if let message = day.errorMessage ?? store.errorMessage {
                    Text(message)
                        .font(.caption)
                        .foregroundStyle(.red)
                        .fixedSize(horizontal: false, vertical: true)
                }

                list
                footer
            }
        }
        .padding(16)
        .task {
            await day.opened()
        }
    }

    // MARK: - Week strip

    private var strip: some View {
        HStack(spacing: 0) {
            Button {
                Task { await day.showPreviousWeek() }
            } label: {
                Image(systemName: "chevron.left")
                    .frame(width: 20, height: 36)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .foregroundStyle(.secondary)
            .help("Previous week")

            ForEach(day.sheet?.days ?? []) { weekDay in
                dayButton(weekDay)
                    .frame(maxWidth: .infinity)
            }

            Button {
                Task { await day.showNextWeek() }
            } label: {
                Image(systemName: "chevron.right")
                    .frame(width: 20, height: 36)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .foregroundStyle(.secondary)
            .help("Next week")
        }
        .frame(minHeight: 50)
        .overlay {
            if day.sheet == nil {
                ProgressView().controlSize(.small)
            }
        }
    }

    private func dayButton(_ weekDay: WeekDay) -> some View {
        let isSelected = weekDay.date == day.sheet?.selectedDate
        let total = weekDay.value + liveHours(on: weekDay.date)

        return Button {
            Task { await day.select(weekDay.date) }
        } label: {
            VStack(spacing: 4) {
                Text(String(weekDay.weekday.prefix(1)))
                    .font(.system(size: 13, weight: isSelected ? .bold : .medium))
                    .foregroundStyle(isSelected ? AnyShapeStyle(Color.white) : weekDay.isToday ? AnyShapeStyle(Color.accentColor) : AnyShapeStyle(.secondary))
                    .frame(width: 26, height: 26)
                    .background {
                        if isSelected {
                            Circle().fill(Color.accentColor)
                        }
                    }

                Text(Hours.display(total))
                    .font(.system(size: 10).monospacedDigit())
                    .foregroundStyle(isSelected ? AnyShapeStyle(Color.accentColor) : total > 0 ? AnyShapeStyle(.secondary) : AnyShapeStyle(.tertiary))
            }
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .help(WeekDates.describe(weekDay.date, template: "EEEEdMMMM"))
    }

    private var title: some View {
        HStack(alignment: .firstTextBaseline) {
            if let sheet = day.sheet {
                Text(day.isToday
                    ? "Today, \(WeekDates.describe(sheet.selectedDate, template: "dMMM"))"
                    : WeekDates.describe(sheet.selectedDate, template: "EEEEdMMM"))
                    .font(.headline)
            }

            Spacer()

            if let sheet = day.sheet {
                Text(Hours.display((Double(sheet.dayTotal) ?? 0) + liveHours(on: sheet.selectedDate)))
                    .font(.system(.headline, design: .rounded))
                    .monospacedDigit()
                    .help("Total for the day")
            }
        }
    }

    /// Time the running timer added since the sheet was loaded, on its day.
    private func liveHours(on date: String) -> Double {
        guard let timer = store.timer, timer.spentOn == date else {
            return 0
        }

        return max(0, store.now.timeIntervalSince(day.loadedAt)) / 3600
    }

    // MARK: - Entries

    @ViewBuilder
    private var list: some View {
        if day.sheet != nil, day.entries.isEmpty {
            VStack(spacing: 6) {
                Image(systemName: "clock")
                    .font(.title2)
                    .foregroundStyle(.tertiary)
                Text("Nothing logged on this day.")
                    .font(.callout)
                    .foregroundStyle(.secondary)
            }
            .frame(maxWidth: .infinity)
            .padding(.vertical, 28)
        } else if day.entries.count > 6 {
            ScrollView {
                rows
            }
            .frame(height: 420)
        } else {
            rows
        }
    }

    private var rows: some View {
        VStack(spacing: 4) {
            ForEach(day.entries) { entry in
                EntryRow(
                    entry: entry,
                    hours: hours(of: entry),
                    isRunning: store.timer?.id == entry.id,
                    canPlay: day.isToday(entry.spentOn),
                    isBusy: store.isBusy,
                    onPlay: { Task { await play(entry) } },
                    onEdit: { edit(entry) },
                    onDelete: { Task { _ = await day.delete(entry) } }
                )
            }
        }
    }

    private func hours(of entry: DayEntry) -> Double {
        store.timer?.id == entry.id ? Double(store.elapsedSeconds) / 3600 : entry.value
    }

    private func play(_ entry: DayEntry) async {
        if store.timer?.id == entry.id {
            await store.stopTimer()
        } else {
            await store.startTimer(entryId: entry.id)
        }

        await day.reload()
    }

    private func edit(_ entry: DayEntry) {
        draft = EntryDraft(
            entry: entry,
            date: entry.spentOn,
            clientId: store.projects.first { $0.id == entry.projectId }?.clientId,
            projectId: entry.projectId,
            hours: entry.value > 0 ? Hours.display(entry.value) : "",
            notes: entry.notes ?? ""
        )
    }

    // MARK: - Footer

    private var footer: some View {
        HStack(spacing: 8) {
            Button {
                draft = EntryDraft(
                    entry: nil,
                    date: day.sheet?.selectedDate ?? "",
                    clientId: store.selectedClientId,
                    projectId: store.selectedProjectId,
                    hours: "",
                    notes: ""
                )
            } label: {
                Label("Add entry", systemImage: "plus")
            }
            .disabled(day.sheet == nil || store.projects.isEmpty)

            if !day.isToday {
                Button("Today") {
                    Task { await day.showToday() }
                }
            }

            Spacer()

            if day.isLoading || day.isBusy || store.isBusy {
                ProgressView().controlSize(.small)
            }
        }
        .controlSize(.small)
    }
}

/// One entry in the day list: client, project and notes on the left, the
/// hours and the play button on the right. A running entry is washed in
/// the accent colour and its hours tick.
private struct EntryRow: View {
    let entry: DayEntry
    let hours: Double
    let isRunning: Bool
    let canPlay: Bool
    let isBusy: Bool
    let onPlay: () -> Void
    let onEdit: () -> Void
    let onDelete: () -> Void

    @Environment(\.panelTheme) private var theme
    @State private var isHovering = false

    private var canEdit: Bool {
        !entry.isReadOnly && !isRunning
    }

    var body: some View {
        HStack(alignment: .center, spacing: 10) {
            Capsule()
                .fill(Color(hex: entry.projectColor) ?? .accentColor)
                .frame(width: 3)
                .padding(.vertical, 2)

            VStack(alignment: .leading, spacing: 1) {
                if let client = entry.clientName {
                    Text(client)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(1)
                }

                Text(entry.projectName ?? "Project")
                    .font(.system(size: 13, weight: .semibold))
                    .lineLimit(2)

                if let notes = entry.notes, !notes.isEmpty {
                    Text(notes)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .lineLimit(2)
                }
            }

            Spacer(minLength: 8)

            Text(Hours.display(hours))
                .font(.system(size: 17, weight: isRunning ? .semibold : .regular, design: .rounded))
                .monospacedDigit()

            playButton
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 8)
        .fixedSize(horizontal: false, vertical: true)
        .modifier(EntryRowSurface(theme: theme, isRunning: isRunning, isHighlighted: isHovering && canEdit))
        .contentShape(Rectangle())
        .onHover { isHovering = $0 }
        .onTapGesture {
            if canEdit {
                onEdit()
            }
        }
        .help(canEdit ? "Click to change" : isRunning ? "Pause the timer to change this entry" : "Billed or locked")
        .contextMenu {
            if canPlay, !entry.isReadOnly {
                Button(isRunning ? "Pause timer" : "Start timer", action: onPlay)
            }
            Button("Edit…", action: onEdit)
                .disabled(!canEdit)
            Button("Delete", role: .destructive, action: onDelete)
                .disabled(!canEdit)
        }
    }

    @ViewBuilder
    private var playButton: some View {
        if entry.isReadOnly {
            Image(systemName: "lock.fill")
                .font(.system(size: 11))
                .foregroundStyle(.secondary)
                .frame(width: 28, height: 28)
        } else if canPlay {
            Button(action: onPlay) {
                Image(systemName: isRunning ? "pause.fill" : "play.fill")
                    .font(.system(size: 11, weight: .bold))
                    .foregroundStyle(isRunning ? AnyShapeStyle(Color.white) : AnyShapeStyle(Color.accentColor))
                    .offset(x: isRunning ? 0 : 1)
                    .frame(width: 28, height: 28)
                    .background {
                        if isRunning {
                            Circle().fill(Color.accentColor)
                                .background(ClockSweep())
                        } else {
                            Circle().strokeBorder(Color.accentColor, lineWidth: 1.5)
                        }
                    }
                    .contentShape(Circle())
            }
            .buttonStyle(.plain)
            .disabled(isBusy)
            .help(isRunning ? "Pause" : "Continue the timer on this entry")
        } else {
            Color.clear.frame(width: 28, height: 28)
        }
    }
}

/// The surface of a row. The running entry is a pane, applied to the row
/// itself: glass behind a row as a separate layer ends up drawn over the
/// text on macOS 26.
private struct EntryRowSurface: ViewModifier {
    let theme: PanelTheme
    let isRunning: Bool
    let isHighlighted: Bool

    func body(content: Content) -> some View {
        if isRunning {
            content.panelPane(theme, cornerRadius: 8)
        } else {
            content.background(
                RoundedRectangle(cornerRadius: 8, style: .continuous).fill(Color.primary.opacity(isHighlighted ? 0.05 : 0))
            )
        }
    }
}

/// A clock hand sweeping around the pause button: a faint dial with a bright
/// arc that goes round like a second hand, so the entry the clock is running
/// on is the one that moves.
private struct ClockSweep: View {
    @State private var isTurning = false
    @Environment(\.accessibilityReduceMotion) private var reduceMotion

    var body: some View {
        ZStack {
            Circle()
                .stroke(Color.accentColor.opacity(0.25), lineWidth: 2)

            Circle()
                .trim(from: 0, to: 0.22)
                .stroke(Color.accentColor, style: StrokeStyle(lineWidth: 2, lineCap: .round))
                .rotationEffect(.degrees(isTurning ? 270 : -90))
        }
        .padding(-5)
        .onAppear {
            guard !reduceMotion else {
                return
            }

            withAnimation(.linear(duration: 2.4).repeatForever(autoreverses: false)) {
                isTurning = true
            }
        }
    }
}
