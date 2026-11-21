import React, { useState, useEffect } from 'react'
import { motion, AnimatePresence } from 'framer-motion'
import { Printer, CheckCircle2, QrCode, RotateCcw, FileDown } from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../lib/api/client'
import { Modal } from '../ui/Modal'
import { Button } from '../ui/Button'
import { toPersianDigits } from '../../lib/numerals'
import { formatToman } from '../../lib/currency'
import { formatJalali } from '../../lib/jalali'
import type { ReceiptPrinterPayload } from '../../types'

interface InvoiceReceiptPrinterProps {
  invoiceId: number | null
  isOpen: boolean
  onClose: () => void
}

/**
 * dqnamo Receipt Printer Component — Exclusively for Invoice Issuance
 * Uses thermal paper feed animation with design system tokens.
 */
export const InvoiceReceiptPrinter: React.FC<InvoiceReceiptPrinterProps> = ({
  invoiceId,
  isOpen,
  onClose,
}) => {
  const [stageIndex, setStageIndex] = useState(0)

  const { data, isLoading } = useQuery<ReceiptPrinterPayload>({
    queryKey: ['invoice-receipt', invoiceId],
    queryFn: async () => {
      const res = await apiClient.get(`/invoices/${invoiceId}/receipt`)
      return res.data
    },
    enabled: isOpen && !!invoiceId,
  })

  const stages = data?.stages || []
  const receipt = data?.receipt

  useEffect(() => {
    if (!isOpen || !data) {
      setStageIndex(0)
      return
    }

    setStageIndex(0)
    const interval = setInterval(() => {
      setStageIndex((prev) => {
        if (prev >= data.stages.length - 1) {
          clearInterval(interval)
          return prev
        }
        return prev + 1
      })
    }, 280)

    return () => clearInterval(interval)
  }, [isOpen, data])

  const currentStage = stages[stageIndex]
  const isFinished = stageIndex >= stages.length - 1

  const handleOpenPrintablePdf = () => {
    if (!invoiceId) return
    window.open(`/api/v1/invoices/${invoiceId}/pdf`, '_blank')
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title="چاپگر حرارتی رسید صورت‌حساب (dqnamo Receipt Printer)"
      description="صدور و چاپ آنی رسید رسمی مشتری با کد رهگیری یکتا"
      maxWidth="md"
    >
      {isLoading || !receipt ? (
        <div className="py-12 text-center text-xs text-slate-400">
          در حال اتصال به موتور چاپگر رسید...
        </div>
      ) : (
        <div className="space-y-4">
          {/* Thermal Printer Hardware Slot Header */}
          <div className="rounded-2xl bg-slate-900 p-4 text-white shadow-inner border border-slate-800">
            <div className="flex items-center justify-between mb-3">
              <div className="flex items-center gap-2">
                <Printer className="h-4 w-4 text-blue-400" />
                <span className="text-xs font-bold tracking-wide">
                  {data.printer_engine.toUpperCase()} ({toPersianDigits(data.paper_width_mm)}mm)
                </span>
              </div>
              <div className="flex items-center gap-2">
                <span
                  className={`h-2.5 w-2.5 rounded-full ${
                    isFinished ? 'bg-emerald-400' : 'bg-amber-400 animate-ping'
                  }`}
                />
                <span className="text-[11px] text-slate-300">
                  {currentStage?.label || 'آماده'}
                </span>
              </div>
            </div>

            {/* Stage Progress Bar */}
            <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
              <motion.div
                className="h-full bg-emerald-400"
                initial={{ width: 0 }}
                animate={{ width: `${currentStage?.progress ?? 100}%` }}
                transition={{ duration: 0.25 }}
              />
            </div>

            {/* Paper Exit Slot */}
            <div className="mt-3 h-2.5 w-full rounded-full bg-slate-950 border border-slate-800 shadow-inner" />
          </div>

          {/* Animated Thermal Paper Receipt Roll */}
          <div className="overflow-hidden px-2">
            <AnimatePresence mode="wait">
              <motion.div
                key={invoiceId}
                initial={{ y: -220, opacity: 0.3 }}
                animate={{ y: 0, opacity: 1 }}
                transition={{
                  duration: 0.8, // --duration-receipt: 800ms
                  ease: [0.0, 0.0, 0.2, 1],
                }}
                className="relative mx-auto w-full rounded-b-2xl bg-amber-50/40 dark:bg-slate-900 border-x border-b-2 border-dashed border-slate-300 dark:border-slate-700 p-5 shadow-lg text-slate-900 dark:text-slate-100"
              >
                {/* Company Header */}
                <div className="text-center border-b border-dashed border-slate-300 dark:border-slate-700 pb-3">
                  <div className="text-sm font-black">{receipt.company_name}</div>
                  <div className="text-[11px] text-slate-500 mt-0.5">
                    {receipt.branch_name} • تلفن: {toPersianDigits(receipt.company_phone)}
                  </div>
                  <div className="mt-2 inline-block px-3 py-0.5 rounded-full bg-slate-200/70 dark:bg-slate-800 text-[11px] font-mono font-bold">
                    شماره رسید: {toPersianDigits(receipt.invoice_number)}
                  </div>
                </div>

                {/* Customer & Date Info */}
                <div className="py-3 border-b border-dashed border-slate-300 dark:border-slate-700 text-xs space-y-1">
                  <div className="flex justify-between">
                    <span className="text-slate-500">مشتری:</span>
                    <span className="font-bold">{receipt.customer_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">تلفن:</span>
                    <span className="font-mono">{toPersianDigits(receipt.customer_phone)}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">تاریخ صدور:</span>
                    <span>{formatJalali(receipt.issue_date)}</span>
                  </div>
                </div>

                {/* Line Items */}
                <div className="py-3 border-b border-dashed border-slate-300 dark:border-slate-700 space-y-2">
                  <div className="flex justify-between text-[11px] font-bold text-slate-400">
                    <span>شرح خدمت / کالا</span>
                    <span>مبلغ کل</span>
                  </div>
                  {receipt.items.map((item, idx) => (
                    <div key={idx} className="flex justify-between items-start text-xs">
                      <div>
                        <div className="font-bold">{item.name}</div>
                        <div className="text-[10px] text-slate-500">
                          {toPersianDigits(item.quantity)} × {formatToman(item.unit_price)}
                        </div>
                      </div>
                      <span className="font-bold">{formatToman(item.total_amount)}</span>
                    </div>
                  ))}
                </div>

                {/* Totals Breakdown */}
                <div className="py-3 border-b border-dashed border-slate-300 dark:border-slate-700 text-xs space-y-1.5">
                  <div className="flex justify-between text-slate-500">
                    <span>جمع جزء:</span>
                    <span>{formatToman(receipt.subtotal)}</span>
                  </div>
                  {receipt.discount_amount > 0 && (
                    <div className="flex justify-between text-emerald-600">
                      <span>تخفیف:</span>
                      <span>- {formatToman(receipt.discount_amount)}</span>
                    </div>
                  )}
                  <div className="flex justify-between text-slate-500">
                    <span>مالیات و عوارض (۹٪):</span>
                    <span>{formatToman(receipt.tax_amount)}</span>
                  </div>
                  <div className="flex justify-between text-sm font-black pt-1 border-t border-slate-200 dark:border-slate-800">
                    <span>مبلغ قابل پرداخت:</span>
                    <span className="text-blue-600 dark:text-blue-400">
                      {formatToman(receipt.total_amount)}
                    </span>
                  </div>
                  <div className="flex justify-between text-[11px] text-emerald-600 font-bold">
                    <span>پرداخت‌شده:</span>
                    <span>{formatToman(receipt.paid_amount)}</span>
                  </div>
                  {receipt.remaining_amount > 0 && (
                    <div className="flex justify-between text-[11px] text-rose-600 font-bold">
                      <span>مانده بدهی:</span>
                      <span>{formatToman(receipt.remaining_amount)}</span>
                    </div>
                  )}
                </div>

                {/* QR & Digital Verification Footer */}
                <div className="pt-3 flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <div className="p-1.5 rounded-lg bg-slate-900 text-white">
                      <QrCode className="h-7 w-7" />
                    </div>
                    <div className="text-[10px] text-slate-500">
                      <div className="font-bold text-slate-700 dark:text-slate-300">
                        تاییدیه دیجیتال ویس‌ریچ
                      </div>
                      <div className="font-mono truncate max-w-[170px]">
                        {receipt.qr_verification}
                      </div>
                    </div>
                  </div>
                  {isFinished && (
                    <span className="inline-flex items-center gap-1 text-xs font-bold text-emerald-600">
                      <CheckCircle2 className="h-4 w-4" />
                      <span>صادر شد</span>
                    </span>
                  )}
                </div>
              </motion.div>
            </AnimatePresence>
          </div>

          {/* Printer Actions */}
          <div className="flex items-center justify-between pt-2">
            <Button
              variant="ghost"
              size="sm"
              icon={<RotateCcw className="h-3.5 w-3.5" />}
              onClick={() => setStageIndex(0)}
            >
              چاپ مجدد رسید
            </Button>

            <div className="flex items-center gap-2">
              <Button
                variant="outline"
                size="sm"
                icon={<FileDown className="h-4 w-4 text-blue-600" />}
                onClick={handleOpenPrintablePdf}
              >
                نسخه چاپی / PDF
              </Button>
              <Button size="sm" onClick={() => window.print()}>
                ارسال به پرینتر
              </Button>
            </div>
          </div>
        </div>
      )}
    </Modal>
  )
}
