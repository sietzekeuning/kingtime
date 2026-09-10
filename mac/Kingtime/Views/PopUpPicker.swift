import AppKit
import SwiftUI

/// An NSPopUpButton that fills its row. SwiftUI's own menu picker on macOS
/// sizes itself to the chosen title and floats in the middle of the panel.
struct PopUpPicker: NSViewRepresentable {
    struct Item {
        let id: Int
        let title: String
    }

    let placeholder: String
    let items: [Item]
    @Binding var selection: Int?

    func makeNSView(context: Context) -> NSPopUpButton {
        let button = NSPopUpButton(frame: .zero, pullsDown: false)
        button.target = context.coordinator
        button.action = #selector(Coordinator.changed(_:))
        button.setContentHuggingPriority(.defaultLow, for: .horizontal)
        button.setContentCompressionResistancePriority(.defaultLow, for: .horizontal)

        return button
    }

    func updateNSView(_ button: NSPopUpButton, context: Context) {
        context.coordinator.picker = self

        button.removeAllItems()
        button.addItem(withTitle: placeholder)
        button.item(at: 0)?.tag = -1

        for item in items {
            button.addItem(withTitle: item.title)
            button.lastItem?.tag = item.id
        }

        if let selection, let index = items.firstIndex(where: { $0.id == selection }) {
            button.selectItem(at: index + 1)
        } else {
            button.selectItem(at: 0)
        }
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(picker: self)
    }

    final class Coordinator: NSObject {
        var picker: PopUpPicker

        init(picker: PopUpPicker) {
            self.picker = picker
        }

        @objc func changed(_ button: NSPopUpButton) {
            let tag = button.selectedTag()
            picker.selection = tag < 0 ? nil : tag
        }
    }
}
