import React from 'react'
import { cn } from '../../lib/utils'

interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  hoverable?: boolean
}

export const Card: React.FC<CardProps> = ({ className, hoverable = false, children, ...props }) => {
  return (
    <div
      className={cn(
        'rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900',
        hoverable && 'transition-shadow duration-200 hover:shadow-md cursor-pointer',
        className
      )}
      {...props}
    >
      {children}
    </div>
  )
}
