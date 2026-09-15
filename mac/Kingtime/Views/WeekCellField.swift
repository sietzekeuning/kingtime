import AppKit
import SwiftUI

/// One cell of the week grid: the hours of a project on a day. Typing hours
/// and leaving the field writes them; emptying it removes the entry behind
/// the cell. A day with several entries, a locked entry or a running timer
/// only shows its hours and opens that day in the browser.
struct WeekCellField: View {
    let cell: WeekCell
    let saving: Bool
    let width: CGFloat
    let onSave: (Double?) -> Void

    @Environment(\.panelTheme) private var theme

    @State private var draft = ""
    @State private var isInvalid = false
    @FocusState private var isFocused: Bool

    var body: some View {
        if cell.isEditable {
            field
        } else {
            readOnly
        }
    }

    private var field: some View {
        TextField("", text: $draft, prompt: Text(Hours.display(0)))
            .panelField(theme, cornerRadius: 5)
            .multilineTextAlignment(.trailing)
            .font(.system(size: 12).monospacedDigit())
            .focused($isFocused)
            .frame(width: width)
            .disabled(saving)
            .opacity(saving ? 0.5 : 1)
            .overlay {
                if isInvalid {
                    RoundedRectangle(cornerRadius: 5).strokeBorder(Color.red, lineWidth: 1)
                }
            }
            .help(cell.notes ?? "")
            .onAppear { draft = displayValue }
            .onChange(of: cell.hours) { _, _ in
                if !isFocused {
                    draft = displayValue
                }
            }
            .onChange(of: isFocused) { _, focused in
                if focused {
                    selectEverything()
                } else {
                    commit()
                }
            }
            .onSubmit {
                commit()
                isFocused = false
            }
            .onExitCommand {
                draft = displayValue
                isInvalid = false
                isFocused = false
            }
    }

    private var readOnly: some View {
        Button {
            NSWorkspace.shared.open(KingtimeClient.baseURL.appendingPathComponent("time-entries").appending(queryItems: [URLQueryItem(name: "date", value: cell.date)]))
        } label: {
            HStack(spacing: 3) {
                if cell.isRunning {
                    Circle().fill(Color.accentColor).frame(width: 5, height: 5)
                } else if cell.isLocked {
                    Image(systemName: "lock.fill").font(.system(size: 8))
                }

                Text(cell.value > 0 ? Hours.display(cell.value) : "·")
            }
            .font(.system(size: 12).monospacedDigit())
            .foregroundStyle(.secondary)
            .frame(width: width, height: 21, alignment: .trailing)
            .contentShape(Rectangle())
        }
        .buttonStyle(.plain)
        .help(cell.readOnlyReason)
    }

    private var displayValue: String {
        cell.value > 0 ? Hours.display(cell.value) : ""
    }

    private func commit() {
        switch Hours.parse(draft) {
        case .invalid:
            isInvalid = true
        case .cleared:
            isInvalid = false

            if cell.value > 0 {
                onSave(nil)
            } else {
                draft = displayValue
            }
        case let .amount(amount):
            isInvalid = false

            if abs(amount - cell.value) < 0.005 {
                draft = displayValue
            } else {
                onSave(amount > 0 ? amount : nil)
            }
        }
    }

    /// Clicking a cell that holds hours should overwrite them, not put the
    /// caret somewhere in the middle. AppKit sets the selection as part of
    /// the click, so this waits for that to be over.
    private func selectEverything() {
        DispatchQueue.main.async {
            (NSApp.keyWindow?.firstResponder as? NSTextView)?.selectAll(nil)
        }
    }
}
