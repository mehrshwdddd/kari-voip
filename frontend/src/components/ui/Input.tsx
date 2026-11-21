import React from 'react'
import { cn } from '../../lib/utils'

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
  hint?: string
  leadingIcon?: React.ReactNode
  trailingIcon?: React.ReactNode
}

export const Input = React.forwardRef<HTMLInputElement, InputProps>(
  ({ className, label, error, hint, leadingIcon, trailingIcon, id, ...props }, ref) => {
    const inputId = id || (label ? label.replace(/\s+/g, '-').toLowerCase() : undefined)

    return (
      <div className="w-full text-right">
        {label && (
          <label
            htmlFor={inputId}
            className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5"
          >
            {label}
          </label>
        )}
        <div className="relative flex items-center">
          {leadingIcon && (
            <div className="absolute right-3.5 flex items-center pointer-events-none text-slate-400">
              {leadingIcon}
            </div>
          )}
          <input
            id={inputId}
            ref={ref}
            className={cn(
              'w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 transition-colors',
              'focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/20',
              'dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500',
              'disabled:bg-slate-50 disabled:text-slate-400 dark:disabled:bg-slate-800',
              leadingIcon ? 'pr-10' : 'pr-3.5',
              trailingIcon ? 'pl-10' : 'pl-3.5',
              error && 'border-red-500 focus:border-red-500 focus:ring-red-500/20',
              className
            )}
            {...props}
          />
          {trailingIcon && (
            <div className="absolute left-3.5 flex items-center text-slate-400">
              {trailingIcon}
            </div>
          )}
        </div>
        {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        {hint && !error && (
          <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">{hint}</p>
        )}
      </div>
    )
  }
)
Input.displayName = 'Input'
