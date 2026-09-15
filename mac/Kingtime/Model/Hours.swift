import Foundation

/// Hours as the week grid reads and writes them. The server speaks decimal
/// with a point ("2.50"); people type whatever their keyboard gives them.
enum Hours {
    enum Parsed {
        /// An empty cell: the entry behind it is removed.
        case cleared
        case amount(Double)
        case invalid
    }

    /// "1,5", "1.5" and "1:30" all mean an hour and a half. Anything that is
    /// not a number, or does not fit in a day, is refused.
    static func parse(_ text: String) -> Parsed {
        let trimmed = text.trimmingCharacters(in: .whitespaces)

        guard !trimmed.isEmpty else {
            return .cleared
        }

        let parts = trimmed.split(separator: ":", omittingEmptySubsequences: false)

        if parts.count == 2 {
            guard let hours = Int(parts[0]), let minutes = Int(parts[1]), hours >= 0, (0 ..< 60).contains(minutes) else {
                return .invalid
            }

            return within(Double(hours) + Double(minutes) / 60)
        }

        guard let amount = Double(trimmed.replacingOccurrences(of: ",", with: ".")) else {
            return .invalid
        }

        return within(amount)
    }

    private static func within(_ amount: Double) -> Parsed {
        guard amount >= 0, amount <= 24 else {
            return .invalid
        }

        return .amount((amount * 100).rounded() / 100)
    }

    /// What goes on the wire: two decimals with a point, whatever the locale.
    static func wire(_ amount: Double) -> String {
        String(format: "%.2f", amount)
    }

    /// "2.50" from the server as this Mac writes it: "2,50" in Dutch.
    static func display(_ raw: String) -> String {
        display(Double(raw) ?? 0)
    }

    static func display(_ amount: Double) -> String {
        formatter.string(from: NSNumber(value: amount)) ?? String(format: "%.2f", amount)
    }

    private static let formatter: NumberFormatter = {
        let formatter = NumberFormatter()
        formatter.numberStyle = .decimal
        formatter.minimumFractionDigits = 2
        formatter.maximumFractionDigits = 2

        return formatter
    }()
}

/// The `Y-m-d` dates the API speaks, kept out of any time zone so a day
/// never slips over a border at midnight.
enum WeekDates {
    static func add(days: Int, to date: String) -> String {
        guard let parsed = wire.date(from: date), let shifted = calendar.date(byAdding: .day, value: days, to: parsed) else {
            return date
        }

        return wire.string(from: shifted)
    }

    /// "2026-09-07" as "7 Sep", in the language of the Mac.
    static func describe(_ date: String, template: String) -> String {
        guard let parsed = wire.date(from: date) else {
            return date
        }

        let formatter = DateFormatter()
        formatter.timeZone = TimeZone(identifier: "UTC")
        formatter.setLocalizedDateFormatFromTemplate(template)

        return formatter.string(from: parsed)
    }

    private static var calendar: Calendar = {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone(identifier: "UTC")!

        return calendar
    }()

    private static let wire: DateFormatter = {
        let formatter = DateFormatter()
        formatter.locale = Locale(identifier: "en_US_POSIX")
        formatter.timeZone = TimeZone(identifier: "UTC")
        formatter.dateFormat = "yyyy-MM-dd"

        return formatter
    }()
}
