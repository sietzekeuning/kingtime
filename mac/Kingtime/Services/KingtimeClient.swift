import Foundation

enum KingtimeError: LocalizedError {
    /// The token was revoked (Settings > API tokens) or never valid.
    case unauthorized
    /// A 422 with Laravel's `errors` bag, keyed by field.
    case validation([String: [String]])
    case throttled
    case server(Int, String?)
    case network(Error)
    case decoding(Error)

    var errorDescription: String? {
        switch self {
        case .unauthorized:
            return "You have been signed out. Sign in again."
        case let .validation(errors):
            return errors.values.first?.first ?? "Check the details and try again."
        case .throttled:
            return "Too many attempts. Wait a minute and try again."
        case let .server(status, message):
            return message ?? "Kingtime answered with an error (\(status))."
        case .network:
            return "Kingtime could not be reached. Check your connection."
        case .decoding:
            return "Kingtime sent an answer this version does not understand."
        }
    }

    /// The first message for one field of a validation error, if any.
    func message(for field: String) -> String? {
        if case let .validation(errors) = self {
            return errors[field]?.first
        }

        return nil
    }
}

/// A thin client for the desktop API. Every call carries the bearer token
/// the app got when it signed in.
struct KingtimeClient {
    /// The Kingtime installation the app talks to. `defaults write
    /// nl.kingtime.mac serverURL http://kingtime.test` points a development
    /// build at a local copy.
    static var baseURL: URL {
        if let custom = UserDefaults.standard.string(forKey: "serverURL"), let url = URL(string: custom) {
            return url
        }

        return URL(string: "https://kingtime.nl")!
    }

    var token: String?

    func signIn(email: String, password: String, code: String?, deviceName: String) async throws -> TokenResponse {
        try await send("POST", "api/desktop/tokens", body: [
            "email": email,
            "password": password,
            "code": code ?? "",
            "device_name": deviceName,
        ])
    }

    func signOut() async throws {
        let _: Empty = try await send("DELETE", "api/desktop/tokens/current")
    }

    func state() async throws -> DesktopState {
        try await send("GET", "api/desktop/state")
    }

    func startTimer(projectId: Int, notes: String) async throws -> DesktopState {
        try await send("POST", "api/desktop/timer", body: ["project_id": projectId, "notes": notes])
    }

    func stopTimer() async throws -> DesktopState {
        try await send("DELETE", "api/desktop/timer")
    }

    func deductIdle(entryId: Int, seconds: Int, stop: Bool) async throws -> DesktopState {
        try await send("POST", "api/desktop/timer/idle", body: ["time_entry_id": entryId, "seconds": seconds, "stop": stop])
    }

    // MARK: - Transport

    private struct Empty: Decodable {}

    private struct LaravelError: Decodable {
        let message: String?
        let errors: [String: [String]]?
    }

    private func send<T: Decodable>(_ method: String, _ path: String, body: [String: Any]? = nil) async throws -> T {
        var request = URLRequest(url: Self.baseURL.appendingPathComponent(path))
        request.httpMethod = method
        request.timeoutInterval = 20
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("Kingtime for Mac/\(Bundle.main.shortVersion)", forHTTPHeaderField: "User-Agent")

        if let token {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        if let body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        }

        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await URLSession.shared.data(for: request)
        } catch {
            throw KingtimeError.network(error)
        }

        let status = (response as? HTTPURLResponse)?.statusCode ?? 0

        switch status {
        case 200 ..< 300:
            if data.isEmpty, let empty = Empty() as? T {
                return empty
            }

            do {
                return try Self.decoder.decode(T.self, from: data)
            } catch {
                throw KingtimeError.decoding(error)
            }
        case 401:
            throw KingtimeError.unauthorized
        case 422:
            let error = try? Self.decoder.decode(LaravelError.self, from: data)
            throw KingtimeError.validation(error?.errors ?? ["message": [error?.message ?? "Invalid request."]])
        case 429:
            throw KingtimeError.throttled
        default:
            let error = try? Self.decoder.decode(LaravelError.self, from: data)
            throw KingtimeError.server(status, error?.message)
        }
    }

    private static let decoder: JSONDecoder = {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase

        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]

        decoder.dateDecodingStrategy = .custom { decoder in
            let container = try decoder.singleValueContainer()
            let string = try container.decode(String.self)

            guard let date = formatter.date(from: string) else {
                throw DecodingError.dataCorruptedError(in: container, debugDescription: "Not an ISO 8601 date: \(string)")
            }

            return date
        }

        return decoder
    }()
}

extension Bundle {
    var shortVersion: String {
        infoDictionary?["CFBundleShortVersionString"] as? String ?? "0"
    }
}
