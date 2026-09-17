import Foundation

/// The week grid of `/api/desktop/week`: Monday to Sunday, one row per
/// project with a cell per day. The same payload the web app draws its
/// timesheet from, so both agree on totals to the cent.

struct WeekSheet: Codable, Equatable {
    let weekStart: String
    let weekEnd: String
    let weekTotal: String
    let days: [WeekDay]
    let rows: [WeekRow]
    /// Projects that had hours the week before, for "copy from last week".
    let previousWeekProjectIds: [Int]
    /// The day the week was asked around, its total and its entries: what
    /// the day view lists.
    let selectedDate: String
    let dayTotal: String
    let entries: [DayEntry]
}

/// One entry of the selected day. Hours of a running entry are the live
/// hours at the moment the server answered.
struct DayEntry: Codable, Equatable, Identifiable {
    let id: Int
    let projectId: Int
    let spentOn: String
    let hours: String
    let notes: String?
    let isBilled: Bool
    let isLocked: Bool
    let isRunning: Bool
    let projectName: String?
    let projectCode: String?
    let projectColor: String?
    let clientName: String?

    var value: Double {
        Double(hours) ?? 0
    }

    /// Billed or locked: it belongs to an invoice and stays as it is.
    var isReadOnly: Bool {
        isBilled || isLocked
    }
}

struct WeekDay: Codable, Equatable, Identifiable {
    let date: String
    let weekday: String
    let totalHours: String
    let isToday: Bool
    let isWeekend: Bool
    let isFuture: Bool

    var id: String { date }
}

struct WeekRow: Codable, Equatable, Identifiable {
    let projectId: Int
    let projectName: String
    let projectCode: String?
    let projectColor: String?
    let clientName: String
    let totalHours: String
    /// Every entry in the row is billed or locked, so nothing can change.
    let isLocked: Bool
    let cells: [WeekCell]

    var id: Int { projectId }
}

struct WeekCell: Codable, Equatable, Identifiable {
    let date: String
    let hours: String
    let entriesCount: Int
    let isLocked: Bool
    let isRunning: Bool
    let notes: String?

    var id: String { date }

    /// A day with several entries, a locked entry or a running timer keeps
    /// its hours; those are edited in the browser.
    var isEditable: Bool {
        !isLocked && !isRunning && entriesCount <= 1
    }

    var value: Double {
        Double(hours) ?? 0
    }

    var readOnlyReason: String {
        if isRunning {
            return "A timer is running on this day. Open it in Kingtime."
        }

        if isLocked {
            return "Billed or locked. Open it in Kingtime."
        }

        return "\(entriesCount) entries on this day. Open it in Kingtime."
    }
}
