import SwiftUI

struct LoginView: View {
    let store: TimerStore

    @State private var email = ""
    @State private var password = ""
    @State private var code = ""
    @FocusState private var focused: Field?

    private enum Field {
        case email, password, code
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 14) {
            HStack(spacing: 10) {
                Image("PanelIcon")
                    .resizable()
                    .frame(width: 36, height: 36)

                VStack(alignment: .leading, spacing: 2) {
                    Text("Kingtime")
                        .font(.headline)
                    Text("Sign in with your Kingtime account")
                        .font(.caption)
                        .foregroundStyle(.secondary)
                }
            }

            VStack(spacing: 8) {
                TextField("Email", text: $email)
                    .textContentType(.username)
                    .focused($focused, equals: .email)

                SecureField("Password", text: $password)
                    .textContentType(.password)
                    .focused($focused, equals: .password)

                if store.needsTwoFactorCode {
                    TextField("Two-factor code", text: $code)
                        .textContentType(.oneTimeCode)
                        .focused($focused, equals: .code)
                }
            }
            .textFieldStyle(.roundedBorder)
            .onSubmit(submit)

            if let message = store.errorMessage {
                Text(message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .fixedSize(horizontal: false, vertical: true)
            }

            Button(action: submit) {
                HStack {
                    if store.isBusy {
                        ProgressView()
                            .controlSize(.small)
                    }
                    Text("Sign in")
                        .frame(maxWidth: .infinity)
                }
            }
            .keyboardShortcut(.defaultAction)
            .buttonStyle(.borderedProminent)
            .controlSize(.large)
            .disabled(store.isBusy || email.isEmpty || password.isEmpty)

            HStack {
                Link("Create an account", destination: KingtimeClient.baseURL.appendingPathComponent("register"))
                Spacer()
                Button("Quit") {
                    NSApp.terminate(nil)
                }
                .buttonStyle(.plain)
                .foregroundStyle(.secondary)
            }
            .font(.caption)
        }
        .padding(16)
        .onAppear {
            focused = .email
        }
        .onChange(of: store.needsTwoFactorCode) { _, needed in
            if needed {
                focused = .code
            }
        }
    }

    private func submit() {
        guard !email.isEmpty, !password.isEmpty, !store.isBusy else {
            return
        }

        Task {
            await store.signIn(email: email, password: password, code: code)
        }
    }
}
