import SwiftUI

/// The chrome of the panel, in both looks. Everything that draws a surface
/// asks for it here, so classic and glass never drift apart.

private struct PanelThemeKey: EnvironmentKey {
    static let defaultValue = PanelTheme.classic
}

extension EnvironmentValues {
    var panelTheme: PanelTheme {
        get { self[PanelThemeKey.self] }
        set { self[PanelThemeKey.self] = newValue }
    }
}

/// A raised block in the panel, the running timer being the one everyone
/// sees. Classic washes it with the tint. Glass hands it to the system
/// where the Mac has Liquid Glass (macOS 26 and up), which draws the
/// material, the rim light and the shadow itself; older systems get a pane
/// that lets the panel's own frosted background through.
struct PanelPane: ViewModifier {
    let theme: PanelTheme
    let tint: Color
    let cornerRadius: CGFloat

    @Environment(\.colorScheme) private var scheme

    private var shape: RoundedRectangle {
        RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)
    }

    func body(content: Content) -> some View {
        switch theme {
        case .classic:
            content.background(tint.opacity(0.10), in: shape)
        case .glass:
            if #available(macOS 26, *) {
                content.glassEffect(.regular.tint(tint.opacity(0.22)), in: shape)
            } else {
                content
                    .background {
                        ZStack {
                            tint.opacity(scheme == .dark ? 0.18 : 0.14)
                            LinearGradient(
                                colors: scheme == .dark
                                    ? [Color.white.opacity(0.14), Color.white.opacity(0.03)]
                                    : [Color.white.opacity(0.55), Color.white.opacity(0.22)],
                                startPoint: .topLeading,
                                endPoint: .bottomTrailing
                            )
                        }
                    }
                    .clipShape(shape)
                    .overlay(shape.strokeBorder(scheme == .dark ? Color.white.opacity(0.22) : Color.black.opacity(0.08), lineWidth: 1))
                    .shadow(color: .black.opacity(scheme == .dark ? 0.24 : 0.10), radius: 5, y: 2)
            }
        }
    }
}

extension View {
    func panelPane(_ theme: PanelTheme, tint: Color = .accentColor, cornerRadius: CGFloat = 10) -> some View {
        modifier(PanelPane(theme: theme, tint: tint, cornerRadius: cornerRadius))
    }

    /// A text field in the panel. On glass the system bezel would fight the
    /// material behind it, so the field becomes a pane of its own.
    @ViewBuilder
    func panelField(_ theme: PanelTheme, cornerRadius: CGFloat = 6) -> some View {
        switch theme {
        case .classic:
            textFieldStyle(.roundedBorder)
        case .glass:
            let shape = RoundedRectangle(cornerRadius: cornerRadius, style: .continuous)

            textFieldStyle(.plain)
                .padding(.horizontal, 6)
                .padding(.vertical, 4)
                .background(Color.primary.opacity(0.06), in: shape)
                .overlay(shape.strokeBorder(Color.primary.opacity(0.14), lineWidth: 1))
        }
    }

    /// The one prominent button a panel has: start, stop, sign in.
    @ViewBuilder
    func panelPrimaryButton(_ theme: PanelTheme, tint: Color = .accentColor) -> some View {
        if #available(macOS 26, *), theme == .glass {
            buttonStyle(.glassProminent).tint(tint)
        } else {
            buttonStyle(.borderedProminent).tint(tint)
        }
    }

    /// Panes that sit near each other read as one piece of glass instead of
    /// a stack of separate sheets.
    @ViewBuilder
    func panelGlass(_ theme: PanelTheme, spacing: CGFloat = 14) -> some View {
        if #available(macOS 26, *), theme == .glass {
            GlassEffectContainer(spacing: spacing) { self }
        } else {
            self
        }
    }
}
