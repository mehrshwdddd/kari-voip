import React, { useState, useRef } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { UploadCloud, File, CheckCircle2, X } from 'lucide-react'
import { cn } from '../../lib/utils'

interface FileUploadProps {
  onFileSelect?: (file: File) => void
  accept?: string
  maxSizeMB?: number
  className?: string
}

export const FileUpload: React.FC<FileUploadProps> = ({
  onFileSelect,
  accept,
  maxSizeMB = 10,
  className,
}) => {
  const [isDragging, setIsDragging] = useState(false)
  const [file, setFile] = useState<File | null>(null)
  const [progress, setProgress] = useState<number>(0)
  const inputRef = useRef<HTMLInputElement>(null)

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = () => {
    setIsDragging(false)
  }

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setIsDragging(false)
    if (e.dataTransfer.files && e.dataTransfer.files[0]) {
      processFile(e.dataTransfer.files[0])
    }
  }

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      processFile(e.target.files[0])
    }
  }

  const processFile = (selectedFile: File) => {
    setFile(selectedFile)
    setProgress(0)

    // Simulate upload progress
    const interval = setInterval(() => {
      setProgress((prev) => {
        if (prev >= 100) {
          clearInterval(interval)
          if (onFileSelect) onFileSelect(selectedFile)
          return 100
        }
        return prev + 25
      })
    }, 150)
  }

  const removeFile = () => {
    setFile(null)
    setProgress(0)
    if (inputRef.current) inputRef.current.value = ''
  }

  return (
    <div className={cn('w-full', className)}>
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        onChange={handleChange}
        className="hidden"
      />

      <AnimatePresence mode="wait">
        {!file ? (
          <motion.div
            key="dropzone"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            onDragOver={handleDragOver}
            onDragLeave={handleDragLeave}
            onDrop={handleDrop}
            onClick={() => inputRef.current?.click()}
            className={cn(
              'flex flex-col items-center justify-center p-6 border-2 border-dashed rounded-2xl cursor-pointer transition-all min-h-[140px]',
              isDragging
                ? 'border-blue-500 bg-blue-50/50 dark:bg-blue-950/20'
                : 'border-slate-300 dark:border-slate-700 hover:border-slate-400 bg-slate-50/40 dark:bg-slate-900/30'
            )}
          >
            <UploadCloud className="h-8 w-8 text-slate-400 mb-2 stroke-1" />
            <p className="text-xs font-semibold text-slate-700 dark:text-slate-300">
              فایل را اینجا بکشید یا برای انتخاب کلیک کنید
            </p>
            <p className="text-[11px] text-slate-400 mt-1">
              حداکثر حجم مجاز: {maxSizeMB} مگابایت
            </p>
          </motion.div>
        ) : (
          <motion.div
            key="preview"
            initial={{ opacity: 0, scale: 0.98 }}
            animate={{ opacity: 1, scale: 1 }}
            exit={{ opacity: 0, scale: 0.98 }}
            className="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs"
          >
            <div className="flex items-center justify-between mb-2">
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-xl bg-blue-50 dark:bg-blue-950/50 text-blue-600">
                  <File className="h-5 w-5" />
                </div>
                <div>
                  <p className="text-xs font-bold text-slate-800 dark:text-slate-200 truncate max-w-[200px]">
                    {file.name}
                  </p>
                  <p className="text-[11px] text-slate-400">
                    {(file.size / (1024 * 1024)).toFixed(2)} مگابایت
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={removeFile}
                className="p-1 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            {/* Progress bar */}
            <div className="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full overflow-hidden">
              <motion.div
                initial={{ width: 0 }}
                animate={{ width: `${progress}%` }}
                className="bg-blue-600 h-full"
              />
            </div>

            {progress === 100 && (
              <div className="flex items-center gap-1 mt-2 text-[11px] text-emerald-600 font-medium">
                <CheckCircle2 className="h-3.5 w-3.5" />
                <span>فایل آماده بارگذاری است</span>
              </div>
            )}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  )
}
