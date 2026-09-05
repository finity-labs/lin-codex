/**
 * The button that opens the help drawer. Pair it with HelpDrawer, or call
 * openCodex() from anywhere instead; the drawer listens for both.
 */

/** The keyboard shortcut HelpDrawer listens for, in case the button wants to show it. */
export const HELP_SHORTCUT = 'Ctrl+/'

export interface HelpButtonProps {
  onClick: () => void
  label?: string
  className?: string
}

export function HelpButton({ onClick, label = 'Help', className }: HelpButtonProps) {
  return (
    <button
      type="button"
      className={['codex-help-button', className].filter(Boolean).join(' ')}
      aria-label={label}
      onClick={onClick}
    >
      {label}
    </button>
  )
}

export default HelpButton
