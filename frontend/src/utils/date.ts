/** "20 Oct", with the year only when it isn't this year ("3 Dec 2025"). Empty for null. */
export function shortDate(iso: string | null | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  return d.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    ...(d.getFullYear() !== new Date().getFullYear() && { year: 'numeric' }),
  })
}

/** shortDate plus the time: "20 Oct, 18:36". */
export function shortDateTime(iso: string | null | undefined): string {
  if (!iso) return ''
  return `${shortDate(iso)}, ${new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}`
}
