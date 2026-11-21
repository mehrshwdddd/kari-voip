import React from 'react'
import { motion } from 'framer-motion'
import { cn } from '../../lib/utils'

export interface DockItem {
  id: string
  label: string
  icon: React.ReactNode
  onClick: () => void
  isActive?: boolean
  badge?: number | string
}

interface MenuDockProps {
  items: DockItem[]
  className?: string
}

export const MenuDock: React.FC<MenuDockProps> = ({ items, className }) => {
  return (
    <nav
      aria-label="منوی دسترسی سریع"
      className={cn(
        'fixed bottom-4 inset-x-0 mx-auto w-fit z-40 flex items-center gap-1.5 p-2 rounded-2xl bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border border-slate-200/80 dark:border-slate-800 shadow-xl shadow-slate-900/10',
        className
      )}
    >
      {items.map((item) => (
        <motion.button
          key={item.id}
          whileHover={{ scale: 1.15, y: -2 }}
          whileTap={{ scale: 0.92 }}
          transition={{ type: 'spring', stiffness: 400, damping: 25 }}
          onClick={item.onClick}
          className={cn(
            'relative flex flex-col items-center justify-center w-12 h-12 rounded-xl text-slate-600 dark:text-slate-300 transition-colors',
            item.isActive && 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60'
          )}
          title={item.label}
        >
          {item.icon}
          {item.badge !== undefined && (
            <span className="absolute top-1 right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
              {item.badge}
            </span>
          )}
          {item.isActive && (
            <motion.div
              layoutId="dock-active-pill"
              className="absolute -bottom-1 w-1.5 h-1.5 rounded-full bg-blue-600 dark:bg-blue-400"
              transition={{ type: 'spring', stiffness: 500, damping: 30 }}
            />
          )}
        </motion.button>
      ))}
    </nav>
  )
}
