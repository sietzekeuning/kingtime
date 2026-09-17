import AppKit

/// The image of the status item: a pill with play or pause at the leading
/// edge and the time after it. Orange with a pause glyph while a timer
/// runs, grey with a play glyph while it is paused. The crown clock only
/// shows while nobody is signed in.
enum StatusItemImage {
    enum Look {
        case signedOut
        case stopped
        case running
    }

    /// The play/pause square at the leading edge: the only part that is a
    /// button.
    static let glyphWidth: CGFloat = 22

    /// The space between the square and the time.
    static let gap: CGFloat = 6

    private static let height: CGFloat = 18
    private static let timeFont = NSFont.monospacedDigitSystemFont(ofSize: 12, weight: .medium)

    static var crown: NSImage? {
        let image = NSImage(named: "MenuBarIcon")
        image?.isTemplate = true

        return image
    }

    /// How wide the pill is, with the time inside it when there is one.
    static func pillWidth(time: String?) -> CGFloat {
        guard let time, !time.isEmpty else {
            return glyphWidth
        }

        return glyphWidth + gap + timeWidth(time) + gap
    }

    private static func timeWidth(_ time: String) -> CGFloat {
        ceil((time as NSString).size(withAttributes: [.font: timeFont]).width)
    }

    static let stoppedColor = NSColor(white: 0.5, alpha: 1)
    static let runningColor = NSColor(red: 0xF2 / 255, green: 0x62 / 255, blue: 0x2A / 255, alpha: 1)

    static func image(for look: Look, time: String?) -> NSImage? {
        switch look {
        case .signedOut:
            return crown
        case .stopped:
            return withPill(glyph: "play.fill", time: time, color: stoppedColor, accessibility: "Continue the timer")
        case .running:
            return withPill(glyph: "pause.fill", time: time, color: runningColor, accessibility: "Pause the timer")
        }
    }

    /// One frame of the change from one look to the other: the pill blends
    /// between grey and orange, and the glyph swaps halfway.
    static func frame(from: Look, to: Look, time: String?, progress: CGFloat) -> NSImage? {
        let fromColor = from == .running ? runningColor : stoppedColor
        let toColor = to == .running ? runningColor : stoppedColor
        let color = fromColor.blended(withFraction: progress, of: toColor) ?? toColor
        let glyph = (progress < 0.5 ? from : to) == .running ? "pause.fill" : "play.fill"

        return withPill(glyph: glyph, time: time, color: color, accessibility: to == .running ? "Pause the timer" : "Continue the timer")
    }

    private static func withPill(glyph name: String, time: String?, color: NSColor, accessibility: String) -> NSImage? {
        let configuration = NSImage.SymbolConfiguration(pointSize: 10, weight: .bold)
            .applying(NSImage.SymbolConfiguration(paletteColors: [.white]))

        guard let glyph = NSImage(systemSymbolName: name, accessibilityDescription: nil)?.withSymbolConfiguration(configuration) else {
            return crown
        }

        let time = time?.trimmingCharacters(in: .whitespaces) ?? ""
        let size = NSSize(width: pillWidth(time: time), height: height)

        let image = NSImage(size: size, flipped: false) { rect in
            // One pill: the coloured square (the button) on the left, a
            // neutral dark field with the time (opens the panel) on the right.
            NSBezierPath(roundedRect: rect, xRadius: 5, yRadius: 5).addClip()

            NSColor(white: 0.2, alpha: 0.92).setFill()
            rect.fill()
            color.setFill()
            NSRect(x: 0, y: 0, width: glyphWidth, height: rect.height).fill()

            let glyphX = name == "play.fill" ? glyphWidth / 2 - glyph.size.width / 2 + 1 : glyphWidth / 2 - glyph.size.width / 2
            glyph.draw(in: NSRect(x: glyphX, y: rect.midY - glyph.size.height / 2, width: glyph.size.width, height: glyph.size.height))

            if !time.isEmpty {
                let attributes: [NSAttributedString.Key: Any] = [.font: timeFont, .foregroundColor: NSColor.white]
                let textSize = (time as NSString).size(withAttributes: attributes)
                (time as NSString).draw(at: NSPoint(x: glyphWidth + gap, y: rect.midY - textSize.height / 2), withAttributes: attributes)
            }

            return true
        }
        image.isTemplate = false
        image.accessibilityDescription = accessibility

        return image
    }
}
