import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Trash2, AlertCircle } from 'lucide-react'
import { cn } from '../../lib/utils'

interface DeleteButtonProps {
  onConfirm: () => void
  label?: string
  confirmLabel?: string
  className?: string
  size?: 'sm' | 'md'
}

export const DeleteButton: React.FC<DeleteButtonProps> = ({
  onConfirm,
  label = 'حذف',
  confirmLabel = 'تایید حذف؟',
  className,
  size = 'md',
}) => {
  const [isConfirming, setIsConfirming] = useState(false)

  useEffect(() => {
    if (!isConfirming) return
    const timer = setTimeout(() => setIsConfirming(false), 3000)
    return () => clearTimeout(timer)
  }, [isConfirming])

  const handleClick = (e: React.MouseEvent) => {
    e.stopPropagation()
    if (isConfirming) {
      onConfirm()
      setIsConfirming(false)
    } else {
      setIsConfirming(true)
    }
  }

  return (
    <motion.button
      type="button"
      onClick={handleClick}
      layout
      transition={{ type: 'spring', stiffness: 500, damping: 30 }}
      className={cn(
        'relative inline-flex items-center justify-center gap-1.5 rounded-xl font-medium transition-all duration-200 cursor-pointer overflow-hidden min-h-[44px]',
        size === 'sm' ? 'px-2.5 py-1 text-xs' : 'px-3.5 py-2 text-sm',
        isConfirming
          ? 'bg-rose-600 text-white shadow-md shadow-rose-600/30'
          : 'bg-rose-50 text-rose-600 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-400 dark:hover:bg-rose-950/60',
        className
      )}
    >
      <AnimatePresence mode="wait" initial={false}>
        {isConfirming ? (
          <motion.span
            key="confirming"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="flex items-center gap-1.5"
          >
            <AlertCircle className="h-4 w-4 stroke-2" />
            <span>{confirmLabel}</span>
          </motion.span>
        ) : (
          <motion.span
            key="idle"
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="flex items-center gap-1.5"
          >
            <Trash2 className="h-4 w-4 stroke-2" />
            <span>{label}</span>
          </motion.span>
        )}
      </AnimatePresence>
    </motion.button>
  )
}
