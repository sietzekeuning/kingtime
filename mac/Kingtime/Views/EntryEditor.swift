import SwiftUI

/// An entry as it is being typed: new (no entry) or a change to one.
struct EntryDraft {
    let entry: DayEntry?
    let date: String
    var clientId: Int?
    var projectId: Int?
    var hours: String
    var notes: String
}

/// Adding or changing an entry in the day view: client, project, hours
/// ("0:30", "0,5" and "0.5" all work) and notes. A new entry on today can
/// be saved and started in one go.
struct EntryEditor: View {
    let store: TimerStore
    let day: DayStore
    @State var draft: EntryDraft
    let onClose: () -> Void

    @Environment(\.panelTheme) private var theme
    @State private var hoursInvalid = false
    @State private var confirmingDelete = false
    @FocusState private var hoursFocused: Bool

    private var isNew: Bool {
        draft.entry == nil
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack {
                Text(isNew ? "New entry" : "Edit entry")
                    .font(.headline)
                Spacer()
                Text(WeekDates.describe(draft.date, template: "EEEEdMMM"))
                    .font(.caption)
                    .foregroundStyle(.secondary)
            }

            PopUpPicker(
                placeholder: "Choose a client",
                items: store.clients.map { PopUpPicker.Item(id: $0.id, title: $0.name) },
                selection: Binding(
                    get: { draft.clientId },
                    set: { clientId in
                        draft.clientId = clientId
                        let projects = store.projects(forClient: clientId)

                        if !projects.contains(where: { $0.id == draft.projectId }) {
                            draft.projectId = projects.count == 1 ? projects.first?.id : nil
                        }
                    }
                )
            )

            PopUpPicker(
                placeholder: draft.clientId == nil ? "Choose a client first" : "Choose a project",
                items: store.projects(forClient: draft.clientId).map { project in
                    PopUpPicker.Item(id: project.id, title: project.code.map { "\(project.name) (\($0))" } ?? project.name)
                },
                selection: $draft.projectId
            )
            .disabled(draft.clientId == nil)

            HStack(spacing: 8) {
                TextField("", text: $draft.hours, prompt: Text("0:00"))
                    .panelField(theme)
                    .multilineTextAlignment(.trailing)
                    .font(.body.monospacedDigit())
                    .frame(width: 72)
                    .focused($hoursFocused)
                    .overlay {
                        if hoursInvalid {
                            RoundedRectangle(cornerRadius: 6).strokeBorder(Color.red, lineWidth: 1)
                        }
                    }
                    .onSubmit { Task { await save(start: false) } }

                TextField("Notes (optional)", text: $draft.notes)
                    .panelField(theme)
                    .onSubmit { Task { await save(start: false) } }
            }

            if let message = day.errorMessage ?? store.errorMessage {
                Text(message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .fixedSize(horizontal: false, vertical: true)
            } else if hoursInvalid {
                Text("Type hours as 0:30, 0,5 or 0.5.")
                    .font(.caption)
                    .foregroundStyle(.red)
            }

            HStack(spacing: 8) {
                if let entry = draft.entry {
                    Button(role: .destructive) {
                        if confirmingDelete {
                            Task {
                                if await day.delete(entry) {
                                    onClose()
                                }
                            }
                        } else {
                            confirmingDelete = true
                        }
                    } label: {
                        Label(confirmingDelete ? "Click again to delete" : "Delete", systemImage: "trash")
                    }
                    .foregroundStyle(confirmingDelete ? .red : .primary)
                }

                Spacer()

                if day.isBusy || store.isBusy {
                    ProgressView().controlSize(.small)
                }

                Button("Cancel", action: onClose)
                    .keyboardShortcut(.cancelAction)

                if isNew, day.isToday(draft.date) {
                    Button {
                        Task { await save(start: true) }
                    } label: {
                        Label("Save and start", systemImage: "play.fill")
                    }
                    .disabled(draft.projectId == nil || day.isBusy)
                }

                Button("Save") {
                    Task { await save(start: false) }
                }
                .panelPrimaryButton(theme)
                .keyboardShortcut(.defaultAction)
                .disabled(draft.projectId == nil || day.isBusy)
            }
            .controlSize(.small)
        }
        .onAppear {
            day.errorMessage = nil
            store.errorMessage = nil
            hoursFocused = true
        }
    }

    private func save(start: Bool) async {
        guard let projectId = draft.projectId else {
            return
        }

        let hours: Double

        switch Hours.parse(draft.hours) {
        case .invalid:
            hoursInvalid = true
            return
        case .cleared:
            hours = 0
        case let .amount(amount):
            hours = amount
        }

        hoursInvalid = false
        let notes = draft.notes.trimmingCharacters(in: .whitespacesAndNewlines)

        if let entry = draft.entry {
            if await day.update(entry, projectId: projectId, hours: hours, notes: notes) {
                onClose()
            }

            return
        }

        guard let id = await day.add(projectId: projectId, date: draft.date, hours: hours, notes: notes) else {
            return
        }

        if start {
            await store.startTimer(entryId: id)
            await day.reload()
        }

        onClose()
    }
}
