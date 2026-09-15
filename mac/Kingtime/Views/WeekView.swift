import Sparkle
import SwiftUI

/// The week in the panel, the same grid as on kingtime.nl: a row per
/// project, a cell per day, the week total always in the corner. Hours are
/// typed straight into the cells and rows are added from the project list.
struct WeekView: View {
    let store: TimerStore
    let week: WeekStore
    let updater: SPUUpdater
    @Binding var mode: PanelMode
    @Binding var theme: PanelTheme

    private let projectWidth: CGFloat = 132
    private let dayWidth: CGFloat = 52
    private let totalWidth: CGFloat = 50

    /// The row whose × was clicked: a second click on the red button does
    /// the deleting, so hours are never one stray click away from gone.
    @State private var rowToRemove: Int?

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            PanelHeader(store: store, updater: updater, mode: $mode, theme: $theme)

            navigation

            if let timer = store.timer {
                runningStrip(timer)
            }

            if let message = week.errorMessage {
                Text(message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .fixedSize(horizontal: false, vertical: true)
            }

            if week.sheet == nil {
                ProgressView()
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 40)
            } else {
                grid
            }

            controls
        }
        .padding(16)
        .task {
            await week.opened()
        }
    }

    // MARK: - Navigation

    private var navigation: some View {
        HStack(spacing: 8) {
            Button {
                Task { await week.showPreviousWeek() }
            } label: {
                Image(systemName: "chevron.left")
            }
            .help("Previous week")

            Button {
                Task { await week.showNextWeek() }
            } label: {
                Image(systemName: "chevron.right")
            }
            .help("Next week")

            Button(week.isCurrentWeek ? "This week" : "Return to this week") {
                Task { await week.showThisWeek() }
            }
            .disabled(week.isCurrentWeek)

            if let sheet = week.sheet {
                Text("\(WeekDates.describe(sheet.weekStart, template: "dMMM")) · \(WeekDates.describe(sheet.weekEnd, template: "dMMMy"))")
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }

            Spacer()

            HStack(spacing: 6) {
                Text("Week total")
                    .font(.caption)
                    .foregroundStyle(.secondary)
                Text(Hours.display(week.sheet?.weekTotal ?? "0.00"))
                    .font(.system(.title3, design: .rounded).weight(.semibold))
                    .monospacedDigit()
            }
        }
        .controlSize(.small)
    }

    private func runningStrip(_ timer: DesktopTimer) -> some View {
        HStack(spacing: 8) {
            Circle()
                .fill(Color(hex: timer.projectColor) ?? .accentColor)
                .frame(width: 8, height: 8)
            Text(timer.projectName)
                .font(.caption.weight(.semibold))
                .lineLimit(1)
            Text(timer.clientName)
                .font(.caption)
                .foregroundStyle(.secondary)
                .lineLimit(1)

            Spacer()

            Text(store.elapsedText)
                .font(.callout.weight(.semibold))
                .monospacedDigit()

            Button("Stop") {
                Task {
                    await store.stopTimer()
                    await week.reload()
                }
            }
            .controlSize(.small)
            .disabled(store.isBusy)
        }
        .padding(.horizontal, 10)
        .padding(.vertical, 6)
        .panelPane(theme, tint: Color(hex: timer.projectColor) ?? .accentColor, cornerRadius: 8)
    }

    // MARK: - Grid

    private var rows: [WeekRow] {
        week.rows(from: store.projects)
    }

    @ViewBuilder
    private var grid: some View {
        if let sheet = week.sheet {
            Grid(alignment: .trailing, horizontalSpacing: 4, verticalSpacing: 3) {
                GridRow {
                    Color.clear.frame(width: projectWidth, height: 1)

                    ForEach(sheet.days) { day in
                        VStack(spacing: 0) {
                            Text(day.weekday)
                                .font(.caption.weight(.medium))
                            Text(WeekDates.describe(day.date, template: "dMMM"))
                                .font(.system(size: 9))
                        }
                        .foregroundStyle(day.isToday ? AnyShapeStyle(Color.accentColor) : AnyShapeStyle(.secondary))
                        .frame(width: dayWidth)
                    }

                    Text("Total")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .frame(width: totalWidth, alignment: .trailing)

                    Color.clear.frame(width: 18, height: 1)
                }

                Divider()

                ForEach(rows) { row in
                    GridRow {
                        project(row)

                        ForEach(row.cells) { cell in
                            WeekCellField(
                                cell: cell,
                                saving: week.savingKey == "\(row.projectId):\(cell.date)",
                                width: dayWidth
                            ) { hours in
                                Task { await week.save(projectId: row.projectId, date: cell.date, hours: hours) }
                            }
                        }

                        Text(Hours.display(row.totalHours))
                            .font(.system(size: 12, weight: .semibold).monospacedDigit())
                            .frame(width: totalWidth, alignment: .trailing)

                        remove(row)
                    }
                }

                Divider()

                GridRow {
                    Color.clear.frame(width: projectWidth, height: 1)

                    ForEach(sheet.days) { day in
                        Text(Hours.display(day.totalHours))
                            .font(.system(size: 12, weight: .semibold).monospacedDigit())
                            .foregroundStyle(day.value > 0 ? AnyShapeStyle(.primary) : AnyShapeStyle(.tertiary))
                            .frame(width: dayWidth, alignment: .trailing)
                    }

                    Text(Hours.display(sheet.weekTotal))
                        .font(.system(size: 12, weight: .semibold).monospacedDigit())
                        .frame(width: totalWidth, alignment: .trailing)

                    Color.clear.frame(width: 18, height: 1)
                }
            }

            if rows.isEmpty {
                Text("Nothing logged this week. Add a row to start.")
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 14)
            }
        }
    }

    private func project(_ row: WeekRow) -> some View {
        HStack(spacing: 6) {
            Circle()
                .fill(Color(hex: row.projectColor) ?? .accentColor)
                .frame(width: 8, height: 8)

            VStack(alignment: .leading, spacing: 0) {
                Text(row.projectName)
                    .font(.system(size: 12, weight: .medium))
                    .lineLimit(1)
                Text(row.clientName)
                    .font(.system(size: 10))
                    .foregroundStyle(.secondary)
                    .lineLimit(1)
            }

            Spacer(minLength: 0)
        }
        .frame(width: projectWidth, alignment: .leading)
        .help(row.projectCode.map { "\(row.projectName) (\($0)) · \(row.clientName)" } ?? "\(row.projectName) · \(row.clientName)")
    }

    @ViewBuilder
    private func remove(_ row: WeekRow) -> some View {
        if row.isLocked {
            Image(systemName: "lock.fill")
                .font(.system(size: 9))
                .foregroundStyle(.secondary)
                .frame(width: 18)
                .help("Billed or locked")
        } else if rowToRemove == row.projectId {
            Button {
                rowToRemove = nil
                Task { await week.removeRow(projectId: row.projectId) }
            } label: {
                Image(systemName: "trash.fill")
                    .font(.system(size: 9))
                    .foregroundStyle(.red)
                    .frame(width: 18, height: 18)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .help("Delete \(Hours.display(row.totalHours)) hours on \(row.projectName) this week. Billed and locked hours stay.")
        } else {
            Button {
                rowToRemove = row.projectId
            } label: {
                Image(systemName: "xmark")
                    .font(.system(size: 9))
                    .foregroundStyle(.secondary)
                    .frame(width: 18, height: 18)
                    .contentShape(Rectangle())
            }
            .buttonStyle(.plain)
            .help("Remove \(row.projectName) from this week")
        }
    }

    // MARK: - Adding rows

    private var addable: [ProjectOption] {
        week.addableProjects(from: store.projects)
    }

    private var controls: some View {
        HStack(spacing: 8) {
            Menu {
                ForEach(store.clients.filter { client in addable.contains { $0.clientId == client.id } }, id: \.id) { client in
                    Menu(client.name) {
                        ForEach(addable.filter { $0.clientId == client.id }) { project in
                            Button(project.code.map { "\(project.name) (\($0))" } ?? project.name) {
                                week.addRow(projectId: project.id)
                            }
                        }
                    }
                }
            } label: {
                Label("Add row", systemImage: "plus")
            }
            .menuStyle(.button)
            .fixedSize()
            .disabled(addable.isEmpty)

            Button {
                week.addLastWeek(from: store.projects)
            } label: {
                Label("Copy from last week", systemImage: "doc.on.doc")
            }
            .disabled(week.lastWeekProjects(from: store.projects).isEmpty)
            .help("Puts last week's projects on the grid as empty rows")

            Spacer()

            if week.isLoading || week.isBusy {
                ProgressView().controlSize(.small)
            }
        }
        .controlSize(.small)
    }
}

extension WeekDay {
    var value: Double {
        Double(totalHours) ?? 0
    }
}
