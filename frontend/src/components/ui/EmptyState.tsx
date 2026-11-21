import React from 'react'
import { Inbox } from 'lucide-react'
import { Button } from './Button'

interface EmptyStateProps {
  title: string
  description?: string
  icon?: React.ReactNode
  actionLabel?: string
  onAction?: () => void
}

export const EmptyState: React.FC<EmptyStateProps> = ({
  title,
  description,
  icon = <Inbox className="h-10 w-10 text-slate-400 stroke-1" />,
  actionLabel,
  onAction,
}) => {
  return (
    <div className="flex min-h-[260px] flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-800 p-8 text-center bg-slate-50/50 dark:bg-slate-900/30">
      <div className="flex h-16 w-16 items-center justify-center rounded-2xl bg-white dark:bg-slate-800 shadow-xs mb-4">
        {icon}
      </div>
      <h4 className="text-sm font-bold text-slate-800 dark:text-slate-200">{title}</h4>
      {description && (
        <p className="mt-1.5 max-w-sm text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          {description}
        </p>
      )}
      {actionLabel && onAction && (
        <div className="mt-5">
          <Button size="sm" onClick={onAction}>
            {actionLabel}
          </Button>
        </div>
      )}
    </div>
  )
}
