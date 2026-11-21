import React, { useState } from 'react'
import {
  Receipt,
  Plus,
  Printer,
  CreditCard,
  Send,
  BookOpen,
  Wallet,
  Trash2,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { InvoiceReceiptPrinter } from '../../../components/billing/InvoiceReceiptPrinter'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type {
  Invoice,
  AccountingLogEntry,
  Contact,
  PaginatedResponse,
} from '../../../types'

export const InvoicesPage: React.FC = () => {
  const queryClient = useQueryClient()

  const [activeTab, setActiveTab] = useState<'invoices' | 'accounting'>('invoices')
  const [createOpen, setCreateOpen] = useState(false)
  const [walletOpen, setWalletOpen] = useState(false)
  const [paymentInvoice, setPaymentInvoice] = useState<Invoice | null>(null)
  const [receiptInvoiceId, setReceiptInvoiceId] = useState<number | null>(null)
  const [feedback, setFeedback] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  // New Invoice form state
  const [selectedContactId, setSelectedContactId] = useState('')
  const [invoiceNotes, setInvoiceNotes] = useState('')
  const [items, setItems] = useState([
    { name: 'مشاوره تخصصی و خدمات', quantity: 1, unit_price: 850000, discount_percent: 0, tax_percent: 9 },
  ])

  // Record Payment form state
  const [payAmount, setPayAmount] = useState('')
  const [payMethod, setPayMethod] = useState<'card' | 'cash' | 'credit' | 'gateway' | 'cheque'>('card')
  const [chequeNumber, setChequeNumber] = useState('')
  const [bankName, setBankName] = useState('بانک ملت')

  // Wallet Topup form state
  const [walletContactId, setWalletContactId] = useState('')
  const [walletAmount, setWalletAmount] = useState('1000000')

  const { data: invoicesData, isLoading } = useQuery<PaginatedResponse<Invoice>>({
    queryKey: ['invoices-list'],
    queryFn: async () => {
      const res = await apiClient.get('/invoices')
      return res.data
    },
  })

  const { data: accountingData } = useQuery<PaginatedResponse<AccountingLogEntry>>({
    queryKey: ['accounting-logs'],
    queryFn: async () => {
      const res = await apiClient.get('/accounting-logs')
      return res.data
    },
    enabled: activeTab === 'accounting',
  })

  const { data: contactsData } = useQuery<PaginatedResponse<Contact>>({
    queryKey: ['contacts-select'],
    queryFn: async () => {
      const res = await apiClient.get('/contacts?per_page=100')
      return res.data
    },
  })

  const refreshBilling = () => {
    queryClient.invalidateQueries({ queryKey: ['invoices-list'] })
    queryClient.invalidateQueries({ queryKey: ['accounting-logs'] })
    queryClient.invalidateQueries({ queryKey: ['contacts-select'] })
    queryClient.invalidateQueries({ queryKey: ['contacts'] })
  }

  const createInvoiceMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/invoices', payload)
      return res.data
    },
    onSuccess: (res) => {
      refreshBilling()
      setCreateOpen(false)
      setFeedback(res.message)
      setReceiptInvoiceId(res.invoice.id)
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || fa.common.error)
    },
  })

  const paymentMutation = useMutation({
    mutationFn: async () => {
      if (!paymentInvoice) return
      const res = await apiClient.post(`/invoices/${paymentInvoice.id}/payment`, {
        amount: parseFloat(payAmount),
        method: payMethod,
        cheque_number: payMethod === 'cheque' ? chequeNumber : undefined,
        bank_name: payMethod === 'cheque' ? bankName : undefined,
      })
      return res.data
    },
    onSuccess: (res) => {
      refreshBilling()
      setPaymentInvoice(null)
      setFeedback(res.message)
    },
    onError: (err: any) => {
      setErrorMsg(
        err.response?.data?.errors?.amount?.[0] ||
          err.response?.data?.message ||
          fa.common.error
      )
    },
  })

  const sendSmsMutation = useMutation({
    mutationFn: async (invoiceId: number) => {
      const res = await apiClient.post(`/invoices/${invoiceId}/send`)
      return res.data
    },
    onSuccess: (res) => {
      setFeedback(res.message)
      refreshBilling()
    },
  })

  const topupMutation = useMutation({
    mutationFn: async () => {
      const targetId = walletContactId || String(contactsData?.data?.[0]?.id || '')
      const res = await apiClient.post(`/contacts/${targetId}/wallet/topup`, {
        amount: parseFloat(walletAmount),
      })
      return res.data
    },
    onSuccess: (res) => {
      refreshBilling()
      setWalletOpen(false)
      setFeedback(res.message)
    },
  })

  const handleAddItemRow = () => {
    setItems([
      ...items,
      { name: '', quantity: 1, unit_price: 0, discount_percent: 0, tax_percent: 9 },
    ])
  }

  const handleRemoveItemRow = (index: number) => {
    if (items.length <= 1) return
    setItems(items.filter((_, idx) => idx !== index))
  }

  const handleCreateSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    setErrorMsg(null)
    const targetContactId = selectedContactId || String(contactsData?.data?.[0]?.id || '')
    if (!targetContactId) return

    createInvoiceMutation.mutate({
      contact_id: parseInt(targetContactId),
      type: 'service',
      notes: invoiceNotes || null,
      items,
    })
  }

  const invoices = invoicesData?.data || []
  const logs = accountingData?.data || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.billing.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.billing.subtitle}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="md"
            icon={<Wallet className="h-4 w-4 text-emerald-600" />}
            onClick={() => setWalletOpen(true)}
          >
            {fa.billing.topupWallet}
          </Button>
          <Button
            size="md"
            icon={<Plus className="h-4 w-4" />}
            onClick={() => {
              setErrorMsg(null)
              setCreateOpen(true)
            }}
          >
            {fa.billing.createInvoice}
          </Button>
        </div>
      </div>

      {feedback && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300">
          {feedback}
        </div>
      )}

      {errorMsg && (
        <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs font-bold text-rose-600 dark:text-rose-400">
          {errorMsg}
        </div>
      )}

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
        <button
          type="button"
          onClick={() => setActiveTab('invoices')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'invoices'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <Receipt className="h-4 w-4" />
          <span>{fa.billing.tabInvoices}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('accounting')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'accounting'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <BookOpen className="h-4 w-4" />
          <span>{fa.billing.tabAccounting}</span>
        </button>
      </div>

      {/* Tab 1: Invoices Table */}
      {activeTab === 'invoices' && (
        <Card className="p-0 overflow-hidden">
          {isLoading ? (
            <div className="p-12 text-center text-xs text-slate-400">
              {fa.common.loading}
            </div>
          ) : invoices.length === 0 ? (
            <div className="p-8">
              <EmptyState
                title="فاکتوری صادر نشده است"
                actionLabel={fa.billing.createInvoice}
                onAction={() => setCreateOpen(true)}
              />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-right text-xs">
                <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                  <tr>
                    <th className="px-4 py-3.5">{fa.billing.invoiceNumber}</th>
                    <th className="px-4 py-3.5">{fa.billing.customer}</th>
                    <th className="px-4 py-3.5">{fa.billing.issueDate}</th>
                    <th className="px-4 py-3.5">{fa.billing.totalAmount}</th>
                    <th className="px-4 py-3.5">{fa.billing.paidAmount}</th>
                    <th className="px-4 py-3.5">{fa.billing.remainingAmount}</th>
                    <th className="px-4 py-3.5">وضعیت</th>
                    <th className="px-4 py-3.5 text-center">عملیات و چاپ رسید</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {invoices.map((inv) => (
                    <tr
                      key={inv.id}
                      className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors"
                    >
                      <td className="px-4 py-3.5 font-mono font-bold text-blue-600">
                        {inv.invoice_number}
                      </td>

                      <td className="px-4 py-3.5">
                        <div className="font-bold text-slate-900 dark:text-slate-100">
                          {inv.contact?.first_name} {inv.contact?.last_name}
                        </div>
                        {inv.contact?.phone_primary && (
                          <div className="text-[11px] font-mono text-slate-400">
                            {toPersianDigits(inv.contact.phone_primary)}
                          </div>
                        )}
                      </td>

                      <td className="px-4 py-3.5 text-slate-500">
                        {formatJalali(inv.issue_date)}
                      </td>

                      <td className="px-4 py-3.5 font-bold text-slate-900 dark:text-slate-100">
                        {formatToman(inv.total_amount)}
                      </td>

                      <td className="px-4 py-3.5 text-emerald-600 font-bold">
                        {formatToman(inv.paid_amount)}
                      </td>

                      <td className="px-4 py-3.5 text-rose-600 font-bold">
                        {formatToman(inv.remaining_amount)}
                      </td>

                      <td className="px-4 py-3.5">
                        {inv.status === 'paid' ? (
                          <Badge variant="success">{fa.billing.statusPaid}</Badge>
                        ) : inv.status === 'partial' ? (
                          <Badge variant="warning">{fa.billing.statusPartial}</Badge>
                        ) : (
                          <Badge variant="primary">{fa.billing.statusSent}</Badge>
                        )}
                      </td>

                      <td className="px-4 py-3.5 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          <Button
                            size="sm"
                            variant="secondary"
                            icon={<Printer className="h-3.5 w-3.5 text-blue-600" />}
                            onClick={() => setReceiptInvoiceId(inv.id)}
                          >
                            {fa.billing.printReceipt}
                          </Button>

                          {Number(inv.remaining_amount) > 0 && (
                            <Button
                              size="sm"
                              className="bg-emerald-600 hover:bg-emerald-700"
                              icon={<CreditCard className="h-3.5 w-3.5" />}
                              onClick={() => {
                                setPaymentInvoice(inv)
                                setPayAmount(String(inv.remaining_amount))
                              }}
                            >
                              {fa.billing.recordPayment}
                            </Button>
                          )}

                          <Button
                            size="sm"
                            variant="ghost"
                            title={fa.billing.sendSmsInvoice}
                            icon={<Send className="h-3.5 w-3.5 text-slate-500" />}
                            onClick={() => sendSmsMutation.mutate(inv.id)}
                          >
                            پیامک
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {/* Tab 2: Accounting Logs Ledger */}
      {activeTab === 'accounting' && (
        <Card className="p-0 overflow-hidden">
          {logs.length === 0 ? (
            <div className="p-8 text-center text-xs text-slate-400">
              هنوز سند حسابداری در دفتر روزنامه ثبت نشده است.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-right text-xs">
                <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                  <tr>
                    <th className="px-4 py-3.5">شناسه سند</th>
                    <th className="px-4 py-3.5">مشتری</th>
                    <th className="px-4 py-3.5">شرح تراکنش</th>
                    <th className="px-4 py-3.5">بدهکار (Debit)</th>
                    <th className="px-4 py-3.5">بستانکار (Credit)</th>
                    <th className="px-4 py-3.5">مانده بدهی پس از سند</th>
                    <th className="px-4 py-3.5">زمان ثبت</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {logs.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                      <td className="px-4 py-3.5 font-mono text-slate-400">
                        #{toPersianDigits(log.id)}
                      </td>
                      <td className="px-4 py-3.5 font-bold">
                        {log.contact?.first_name} {log.contact?.last_name}
                      </td>
                      <td className="px-4 py-3.5">{log.description}</td>
                      <td className="px-4 py-3.5 text-rose-600 font-bold">
                        {Number(log.debit) > 0 ? formatToman(log.debit) : '-'}
                      </td>
                      <td className="px-4 py-3.5 text-emerald-600 font-bold">
                        {Number(log.credit) > 0 ? formatToman(log.credit) : '-'}
                      </td>
                      <td className="px-4 py-3.5 font-bold">
                        {formatToman(log.balance_after)}
                      </td>
                      <td className="px-4 py-3.5 text-[11px] text-slate-400">
                        {formatJalali(log.created_at, true)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {/* Create Invoice Modal */}
      <Modal
        isOpen={createOpen}
        onClose={() => setCreateOpen(false)}
        title={fa.billing.createInvoice}
        description="افزودن ردیف‌های کالا یا خدمات، محاسبه خودکار مالیات بر ارزش افزوده و صدور آنی رسید"
        maxWidth="xl"
      >
        <form onSubmit={handleCreateSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              انتخاب مشتری
            </label>
            <select
              value={selectedContactId}
              onChange={(e) => setSelectedContactId(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              {contactsData?.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.full_name} — موجودی کیف پول: {formatToman(c.credit_balance)}
                </option>
              ))}
            </select>
          </div>

          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 dark:text-slate-300">
                اقلام و خدمات فاکتور:
              </span>
              <Button
                type="button"
                size="sm"
                variant="outline"
                icon={<Plus className="h-3.5 w-3.5" />}
                onClick={handleAddItemRow}
              >
                افزودن ردیف
              </Button>
            </div>

            {items.map((item, idx) => (
              <div
                key={idx}
                className="grid grid-cols-1 sm:grid-cols-12 gap-2.5 p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 items-end"
              >
                <div className="sm:col-span-5">
                  <Input
                    label="شرح خدمت / کالا"
                    value={item.name}
                    onChange={(e) => {
                      const next = [...items]
                      next[idx].name = e.target.value
                      setItems(next)
                    }}
                    required
                  />
                </div>
                <div className="sm:col-span-2">
                  <Input
                    label="تعداد"
                    type="number"
                    dir="ltr"
                    value={String(item.quantity)}
                    onChange={(e) => {
                      const next = [...items]
                      next[idx].quantity = parseFloat(e.target.value) || 1
                      setItems(next)
                    }}
                    required
                  />
                </div>
                <div className="sm:col-span-3">
                  <Input
                    label="مبلغ واحد (تومان)"
                    type="number"
                    dir="ltr"
                    value={String(item.unit_price)}
                    onChange={(e) => {
                      const next = [...items]
                      next[idx].unit_price = parseFloat(e.target.value) || 0
                      setItems(next)
                    }}
                    required
                  />
                </div>
                <div className="sm:col-span-2 flex justify-end">
                  <button
                    type="button"
                    onClick={() => handleRemoveItemRow(idx)}
                    className="p-2.5 rounded-xl text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/40"
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              </div>
            ))}
          </div>

          <Input
            label="توضیحات فاکتور (اختیاری)"
            value={invoiceNotes}
            onChange={(e) => setInvoiceNotes(e.target.value)}
          />

          <div className="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
            <Button
              type="button"
              variant="outline"
              onClick={() => setCreateOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={createInvoiceMutation.isPending}>
              صدور فاکتور و نمایش چاپگر رسید
            </Button>
          </div>
        </form>
      </Modal>

      {/* Record Payment Modal */}
      <Modal
        isOpen={!!paymentInvoice}
        onClose={() => setPaymentInvoice(null)}
        title={`${fa.billing.recordPayment} — فاکتور ${paymentInvoice?.invoice_number || ''}`}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            paymentMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label="مبلغ دریافتی (تومان)"
            type="number"
            dir="ltr"
            value={payAmount}
            onChange={(e) => setPayAmount(e.target.value)}
            required
          />

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              روش دریافت وجه
            </label>
            <select
              value={payMethod}
              onChange={(e: any) => setPayMethod(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              <option value="card">{fa.billing.paymentMethodCard}</option>
              <option value="cash">{fa.billing.paymentMethodCash}</option>
              <option value="credit">{fa.billing.paymentMethodCredit}</option>
              <option value="gateway">{fa.billing.paymentMethodGateway}</option>
              <option value="cheque">{fa.billing.paymentMethodCheque}</option>
            </select>
          </div>

          {payMethod === 'cheque' && (
            <div className="grid grid-cols-2 gap-3">
              <Input
                label="شماره صیادی چک"
                dir="ltr"
                value={chequeNumber}
                onChange={(e) => setChequeNumber(e.target.value)}
                required
              />
              <Input
                label="نام بانک"
                value={bankName}
                onChange={(e) => setBankName(e.target.value)}
                required
              />
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setPaymentInvoice(null)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={paymentMutation.isPending}>
              ثبت نهایی پرداخت
            </Button>
          </div>
        </form>
      </Modal>

      {/* Customer Wallet Top-up Modal */}
      <Modal
        isOpen={walletOpen}
        onClose={() => setWalletOpen(false)}
        title={fa.billing.topupWallet}
        description="افزایش موجودی کیف پول مشتری جهت کسر خودکار در فاکتورها"
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            topupMutation.mutate()
          }}
          className="space-y-4"
        >
          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              انتخاب مشتری
            </label>
            <select
              value={walletContactId}
              onChange={(e) => setWalletContactId(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              {contactsData?.data?.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.full_name} (موجودی فعلی: {formatToman(c.credit_balance)})
                </option>
              ))}
            </select>
          </div>

          <Input
            label="مبلغ افزایش اعتبار (تومان)"
            type="number"
            dir="ltr"
            value={walletAmount}
            onChange={(e) => setWalletAmount(e.target.value)}
            required
          />

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setWalletOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={topupMutation.isPending}>
              شارژ کیف پول
            </Button>
          </div>
        </form>
      </Modal>

      {/* dqnamo Receipt Printer Component */}
      <InvoiceReceiptPrinter
        invoiceId={receiptInvoiceId}
        isOpen={!!receiptInvoiceId}
        onClose={() => setReceiptInvoiceId(null)}
      />
    </div>
  )
}
