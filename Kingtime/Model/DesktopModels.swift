import Foundation

/// The payloads of the desktop API (`/api/desktop/*`) on kingtime.nl.
/// Property names follow the JSON through a snake_case decoding strategy.

struct DesktopUser: Codable, Equatable {
    let id: Int
    let name: String
    let email: String
}

struct ProjectOption: Codable, Identifiable, Equatable {
    let id: Int
    let name: String
    let code: String?
    let color: String?
    let clientId: Int
    let clientName: String
    let isBillable: Bool
}

/// The running timer: the seconds banked before the timer was (re)started
/// plus the moment it started, so the app can run a clock of its own that
/// agrees with the server to the second.
struct DesktopTimer: Codable, Identifiable, Equatable {
    let id: Int
    let projectId: Int
    let projectName: String
    let projectColor: String?
    let clientId: Int
    let clientName: String
    let spentOn: String
    let notes: String?
    let secondsBeforeTimer: Int
    let timerStartedAt: Date

    func elapsedSeconds(at serverNow: Date) -> Int {
        secondsBeforeTimer + max(0, Int(serverNow.timeIntervalSince(timerStartedAt)))
    }
}

struct DesktopState: Codable {
    let user: DesktopUser
    let projects: [ProjectOption]
    let timer: DesktopTimer?
    let serverTime: Date
}

struct TokenResponse: Codable {
    let token: String
    let user: DesktopUser
}
