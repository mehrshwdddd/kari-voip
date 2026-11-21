import { toPersianDigits } from '../numerals'

/**
 * Format amounts in Toman with thousand separators and Persian digits.
 */
export function formatToman(amount: number | string | null | undefined): string {
  if (amount === null || amount === undefined || amount === '') return '۰ تومان'
  const numeric = typeof amount === 'number' ? amount : parseFloat(amount)
  if (isNaN(numeric)) return '۰ تومان'

  const formatted = Math.round(numeric).toLocaleString('en-US')
  return `${toPersianDigits(formatted)} تومان`
}
