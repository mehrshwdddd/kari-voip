import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { cn } from '../../lib/utils'

export interface ContextMenuItem {
  id: string
  label: string
  icon?: React.ReactNode
  danger?: boolean
  onClick: () => void
}

interface ContextMenuProps {
  children: React.ReactNode
  items: ContextMenuItem[]
  className?: string
}

export const ContextMenu: React.FC<ContextMenuProps> = ({ children, items, className }) => {
  const [position, setPosition] = useState<{ x: number; y: number } | null>(null)

  const handleContextMenu = (e: React.MouseEvent) => {
    e.preventDefault()
    setPosition({ x: e.clientX, y: e.clientY })
  }

  useEffect(() => {
    const handleClickOutside = () => setPosition(null)
    if (position) {
      window.addEventListener('click', handleClickOutside)
    }
    return () => window.removeEventListener('click', handleClickOutside)
  }, [position])

  return (
    <div onContextMenu={handleContextMenu} className={cn('relative', className)}>
      {children}

      <AnimatePresence>
        {position && (
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.95 }}
            transition={{ duration: 0.15 }}
            style={{
              position: 'fixed',
              top: Math.min(position.y, window.innerHeight - 200),
              left: Math.min(position.x, window.innerWidth - 180),
              zIndex: 60,
            }}
            className="w-48 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl p-1.5 space-y-0.5"
          >
            {items.map((item) => (
              <button
                key={item.id}
                onClick={() => {
                  item.onClick()
                  setPosition(null)
                }}
                className={cn(
                  'w-full flex items-center gap-2 px-3 py-2 text-xs font-medium rounded-lg transition-colors text-right cursor-pointer',
                  item.danger
                    ? 'text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40'
                    : 'text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800'
                )}
              >
                {item.icon}
                <span>{item.label}</span>
              </button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
