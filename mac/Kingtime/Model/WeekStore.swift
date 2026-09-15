import Foundation
import Observation

/// The week grid in the panel: which week is shown, its rows and totals,
/// and the writes that go back to Kingtime. Every write answers with the
/// whole week, so the totals in the panel are never a guess.
@MainActor
@Observable
final class WeekStore {
    private(set) var sheet: WeekSheet?
    private(set) var isLoading = false
    private(set) var isBusy = false

    /// "projectId:date" of the cell being written, so it can grey out.
    private(set) var savingKey: String?
    var errorMessage: String?

    /// Any day in the week on show; nil is the week around today.
    private(set) var anchor: String?

    /// Projects added to the grid by hand: a row that has no hours yet.
    private(set) var extraProjectIds: [Int] = []

    private var client = KingtimeClient()
    private var isDemo = false

    // MARK: - Rows

    /// The rows of the week, with the hand-added projects after them.
    func rows(from projects: [ProjectOption]) -> [WeekRow] {
        guard let sheet else {
            return []
        }

        return sheet.rows + extraProjectIds.compactMap { id in
            projects.first { $0.id == id }.map { emptyRow(for: $0, days: sheet.days) }
        }
    }

    /// Projects that are not on the grid yet, in the order they came in.
    func addableProjects(from projects: [ProjectOption]) -> [ProjectOption] {
        let shown = Set((sheet?.rows.map(\.projectId) ?? []) + extraProjectIds)

        return projects.filter { !shown.contains($0.id) }
    }

    /// The projects of last week that are not on the grid yet.
    func lastWeekProjects(from projects: [ProjectOption]) -> [ProjectOption] {
        guard let ids = sheet?.previousWeekProjectIds else {
            return []
        }

        return addableProjects(from: projects).filter { ids.contains($0.id) }
    }

    func addRow(projectId: Int) {
        guard !extraProjectIds.contains(projectId) else {
            return
        }

        extraProjectIds.append(projectId)
    }

    func addLastWeek(from projects: [ProjectOption]) {
        for project in lastWeekProjects(from: projects) {
            addRow(projectId: project.id)
        }
    }

    private func emptyRow(for project: ProjectOption, days: [WeekDay]) -> WeekRow {
        WeekRow(
            projectId: project.id,
            projectName: project.name,
            projectCode: project.code,
            projectColor: project.color,
            clientName: project.clientName,
            totalHours: "0.00",
            isLocked: false,
            cells: days.map { day in
                WeekCell(date: day.date, hours: "0.00", entriesCount: 0, isLocked: false, isRunning: false, notes: nil)
            }
        )
    }

    // MARK: - Loading

    /// Called when the week comes into view: the first time it loads, after
    /// that it only catches up on what changed elsewhere.
    func opened() async {
        await load(around: anchor)
    }

    func reload() async {
        guard sheet != nil else {
            return
        }

        await load(around: anchor, quietly: true)
    }

    func showPreviousWeek() async {
        await load(around: WeekDates.add(days: -7, to: sheet?.weekStart ?? today))
    }

    func showNextWeek() async {
        await load(around: WeekDates.add(days: 7, to: sheet?.weekStart ?? today))
    }

    func showThisWeek() async {
        await load(around: nil)
    }

    var isCurrentWeek: Bool {
        sheet?.days.contains { $0.isToday } ?? false
    }

    private func load(around date: String?, quietly: Bool = false) async {
        guard !isDemo else {
            return
        }

        anchor = date

        guard ensureToken() else {
            return
        }

        isLoading = sheet == nil || !quietly
        defer { isLoading = false }

        do {
            apply(try await client.week(around: date))

            if !quietly {
                errorMessage = nil
            }
        } catch {
            report(error, quietly: quietly)
        }
    }

    // MARK: - Writing

    func save(projectId: Int, date: String, hours: Double?) async {
        let key = "\(projectId):\(date)"
        savingKey = key
        defer {
            if savingKey == key {
                savingKey = nil
            }
        }

        await perform {
            try await self.client.saveCell(projectId: projectId, date: date, hours: hours)
        }
    }

    func removeRow(projectId: Int) async {
        guard let sheet else {
            return
        }

        // A row that only exists in the panel is dropped without asking.
        guard sheet.rows.contains(where: { $0.projectId == projectId }) else {
            extraProjectIds.removeAll { $0 == projectId }

            return
        }

        await perform {
            try await self.client.deleteRow(projectId: projectId, weekStart: sheet.weekStart)
        }
    }

    private func perform(_ call: () async throws -> WeekSheet) async {
        guard !isDemo, ensureToken() else {
            return
        }

        errorMessage = nil
        isBusy = true
        defer { isBusy = false }

        do {
            apply(try await call())
        } catch {
            report(error, quietly: false)
        }
    }

    private func apply(_ sheet: WeekSheet) {
        self.sheet = sheet

        // A row that now carries hours comes from the server; drop the copy.
        let saved = Set(sheet.rows.map(\.projectId))
        extraProjectIds.removeAll { saved.contains($0) }
    }

    private func report(_ error: Error, quietly: Bool) {
        if case KingtimeError.unauthorized = error {
            client.token = nil
        }

        if !quietly {
            errorMessage = error.localizedDescription
        }
    }

    // MARK: - Session

    /// The token is read once and kept; signing out throws it away.
    @discardableResult
    private func ensureToken() -> Bool {
        if client.token == nil {
            client.token = Keychain.read("token")
        }

        return client.token != nil
    }

    func forgetSession() {
        client.token = nil
        sheet = nil
        anchor = nil
        extraProjectIds = []
        errorMessage = nil
    }

    private var today: String {
        ISO8601DateFormatter.string(from: Date(), timeZone: .current, formatOptions: [.withFullDate])
    }

    // MARK: - Demo

    /// Fixed, made-up hours for the screenshots on kingtime.nl: no token,
    /// no server (see TimerStore.loadDemo for the matching projects).
    func loadDemo() {
        isDemo = true

        let dates = (0 ... 6).map { WeekDates.add(days: $0, to: "2026-09-07") }
        let weekdays = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"]
        let byProject: [(Int, String, String?, String, [String])] = [
            (1, "Website redesign", "#F2622A", "Acme", ["2.00", "5.00", "2.00", "3.50", "1.00", "0.00", "0.00"]),
            (2, "Support", "#3B82F6", "Acme", ["1.00", "0.00", "1.50", "0.00", "2.00", "0.00", "0.00"]),
            (3, "Brand identity", "#10B981", "Globex", ["4.00", "2.00", "3.00", "4.00", "4.00", "0.00", "0.00"]),
        ]

        let rows = byProject.map { id, name, color, client, hours in
            WeekRow(
                projectId: id,
                projectName: name,
                projectCode: nil,
                projectColor: color,
                clientName: client,
                totalHours: Hours.wire(hours.reduce(0) { $0 + (Double($1) ?? 0) }),
                isLocked: false,
                cells: zip(dates, hours).map { date, amount in
                    WeekCell(date: date, hours: amount, entriesCount: amount == "0.00" ? 0 : 1, isLocked: false, isRunning: false, notes: nil)
                }
            )
        }

        let dayTotals = (0 ... 6).map { index in
            Hours.wire(rows.reduce(0) { $0 + (Double($1.cells[index].hours) ?? 0) })
        }

        sheet = WeekSheet(
            weekStart: dates[0],
            weekEnd: dates[6],
            weekTotal: Hours.wire(dayTotals.reduce(0) { $0 + (Double($1) ?? 0) }),
            days: zip(zip(dates, weekdays), dayTotals).map { pair, total in
                WeekDay(date: pair.0, weekday: pair.1, totalHours: total, isToday: pair.0 == dates[3], isWeekend: pair.1 == "Sat" || pair.1 == "Sun", isFuture: false)
            },
            rows: rows,
            previousWeekProjectIds: []
        )
    }
}
