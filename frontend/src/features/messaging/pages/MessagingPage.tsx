import React, { useState } from 'react'
import {
  MessageSquare,
  Send,
  Plus,
  FileCode2,
  CheckCircle2,
  ShieldCheck,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { DeleteButton } from '../../../components/devigner/DeleteButton'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type {
  MessageTemplate,
  MessageLog,
  PaginatedResponse,
} from '../../../types'

export const MessagingPage: React.FC = () => {
  const queryClient = useQueryClient()

  const [activeTab, setActiveTab] = useState<'logs' | 'templates'>('logs')
  const [sendOpen, setSendOpen] = useState(false)
  const [tplOpen, setTplOpen] = useState(false)
  const [feedback, setFeedback] = useState<string | null>(null)

  // Send SMS form state
  const [toPhone, setToPhone] = useState('09121112233')
  const [selectedTemplateId, setSelectedTemplateId] = useState('')
  const [customBody, setCustomBody] = useState('')
  const [customerNameVar, setCustomerNameVar] = useState('سهراب سپهری')

  // New Template form state
  const [tplName, setTplName] = useState('')
  const [tplCategory, setTplCategory] = useState<'appointment' | 'invoice' | 'campaign' | 'custom'>('appointment')
  const [tplBody, setTplBody] = useState(
    'مشتری گرامی {customer_name}، نوبت شما برای تاریخ {appointment_date} ثبت شد.'
  )

  const { data: logsData, isLoading } = useQuery<PaginatedResponse<MessageLog>>({
    queryKey: ['message-logs'],
    queryFn: async () => {
      const res = await apiClient.get('/messages/logs')
      return res.data
    },
  })

  const { data: templatesData } = useQuery<{ templates: MessageTemplate[] }>({
    queryKey: ['message-templates'],
    queryFn: async () => {
      const res = await apiClient.get('/messages/templates')
      return res.data
    },
  })

  const sendMutation = useMutation({
    mutationFn: async () => {
      const payload: any = {
        to_address: toPhone,
        variables: {
          customer_name: customerNameVar,
          appointment_date: formatJalali(new Date()),
          appointment_time: '۱۰:۰۰',
          service_name: 'مشاوره تخصصی',
          booking_number: 'APT-1405',
          invoice_number: 'VR-1001',
          total_amount: '۸۷۲,۰۰۰',
        },
      }
      if (selectedTemplateId) {
        payload.template_id = parseInt(selectedTemplateId)
      } else {
        payload.body = customBody
      }
      const res = await apiClient.post('/messages/send', payload)
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['message-logs'] })
      setSendOpen(false)
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 3500)
    },
  })

  const createTplMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/messages/templates', {
        name: tplName,
        category: tplCategory,
        body: tplBody,
      })
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['message-templates'] })
      setTplOpen(false)
      setTplName('')
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 3500)
    },
  })

  const deleteTplMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/messages/templates/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['message-templates'] })
    },
  })

  const logs = logsData?.data || []
  const templates = templatesData?.templates || []

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.messaging.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.messaging.subtitle}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="md"
            icon={<Plus className="h-4 w-4 text-blue-600" />}
            onClick={() => setTplOpen(true)}
          >
            {fa.messaging.newTemplate}
          </Button>
          <Button
            size="md"
            icon={<Send className="h-4 w-4" />}
            onClick={() => setSendOpen(true)}
          >
            {fa.messaging.sendQuickSms}
          </Button>
        </div>
      </div>

      {feedback && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300">
          {feedback}
        </div>
      )}

      {/* Active Providers Failover Status Banner */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card className="p-4 flex items-center justify-between border-emerald-100 dark:border-emerald-900/40">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <div>
              <div className="text-xs font-black text-slate-900 dark:text-slate-100">
                اپراتور اصلی: کاوه‌نگار (KavenegarAdapter)
              </div>
              <div className="text-[11px] text-slate-400 font-mono">
                خط ارسالی: ۱۰۰۰۸۶۶۳ • اولویت ۱
              </div>
            </div>
          </div>
          <Badge variant="success">فعال</Badge>
        </Card>

        <Card className="p-4 flex items-center justify-between border-blue-100 dark:border-blue-900/40">
          <div className="flex items-center gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <div>
              <div className="text-xs font-black text-slate-900 dark:text-slate-100">
                اپراتور پشتیبان (Failover): قاصدک (GhasedakAdapter)
              </div>
              <div className="text-[11px] text-slate-400 font-mono">
                خط ارسالی: ۳۰۰۰۵۰۸۸ • اولویت ۲ (سوئیچ خودکار)
              </div>
            </div>
          </div>
          <Badge variant="primary">آماده جایگزینی</Badge>
        </Card>
      </div>

      {/* Tabs */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-800">
        <button
          type="button"
          onClick={() => setActiveTab('logs')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'logs'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <MessageSquare className="h-4 w-4" />
          <span>{fa.messaging.tabLogs}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('templates')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'templates'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <FileCode2 className="h-4 w-4" />
          <span>{fa.messaging.tabTemplates}</span>
        </button>
      </div>

      {/* Tab 1: Message Logs */}
      {activeTab === 'logs' && (
        <Card className="p-0 overflow-hidden">
          {isLoading ? (
            <div className="p-12 text-center text-xs text-slate-400">
              {fa.common.loading}
            </div>
          ) : logs.length === 0 ? (
            <div className="p-8">
              <EmptyState
                title="پیامکی ارسال نشده است"
                actionLabel={fa.messaging.sendQuickSms}
                onAction={() => setSendOpen(true)}
              />
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-right text-xs">
                <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                  <tr>
                    <th className="px-4 py-3.5">گیرنده</th>
                    <th className="px-4 py-3.5">متن پیامک ارسال‌شده</th>
                    <th className="px-4 py-3.5">قالب</th>
                    <th className="px-4 py-3.5">درگاه</th>
                    <th className="px-4 py-3.5">وضعیت</th>
                    <th className="px-4 py-3.5">زمان ارسال</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {logs.map((log) => (
                    <tr key={log.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50">
                      <td className="px-4 py-3.5">
                        <div className="font-mono font-bold text-slate-900 dark:text-slate-100">
                          {toPersianDigits(log.to_address)}
                        </div>
                        {log.contact && (
                          <div className="text-[11px] text-slate-400">
                            {log.contact.first_name} {log.contact.last_name}
                          </div>
                        )}
                      </td>
                      <td className="px-4 py-3.5 max-w-md leading-relaxed">
                        {log.body}
                      </td>
                      <td className="px-4 py-3.5 text-slate-500">
                        {log.template?.name || 'ارسال مستقیم'}
                      </td>
                      <td className="px-4 py-3.5 font-mono text-[11px]">
                        {log.provider}
                      </td>
                      <td className="px-4 py-3.5">
                        <Badge variant={log.status === 'sent' ? 'success' : 'danger'}>
                          {log.status === 'sent' ? 'ارسال موفق' : 'خطا'}
                        </Badge>
                      </td>
                      <td className="px-4 py-3.5 text-[11px] text-slate-400">
                        {formatJalali(log.sent_at || log.created_at, true)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      )}

      {/* Tab 2: Message Templates */}
      {activeTab === 'templates' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          {templates.map((tpl) => (
            <Card key={tpl.id} className="p-5 flex flex-col justify-between space-y-4">
              <div>
                <div className="flex items-center justify-between mb-2">
                  <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
                    {tpl.name}
                  </h3>
                  <Badge variant="primary" size="sm">
                    {tpl.category}
                  </Badge>
                </div>
                <p className="text-xs text-slate-600 dark:text-slate-300 leading-relaxed bg-slate-50 dark:bg-slate-800/50 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                  {tpl.body}
                </p>
              </div>

              <div className="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
                <div className="flex flex-wrap gap-1">
                  {tpl.variables?.map((v) => (
                    <span
                      key={v}
                      className="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-300 font-mono text-[10px]"
                    >
                      {`{${v}}`}
                    </span>
                  ))}
                </div>
                <DeleteButton
                  size="sm"
                  onConfirm={() => deleteTplMutation.mutate(tpl.id)}
                />
              </div>
            </Card>
          ))}
        </div>
      )}

      {/* Send SMS Modal */}
      <Modal
        isOpen={sendOpen}
        onClose={() => setSendOpen(false)}
        title={fa.messaging.sendQuickSms}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            sendMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label={fa.messaging.recipientPhone}
            dir="ltr"
            value={toPhone}
            onChange={(e) => setToPhone(e.target.value)}
            required
          />

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              انتخاب قالب آماده (یا ارسال متن آزاد)
            </label>
            <select
              value={selectedTemplateId}
              onChange={(e) => setSelectedTemplateId(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              <option value="">متن دلخواه (بدون قالب)</option>
              {templates.map((t) => (
                <option key={t.id} value={t.id}>
                  قالب: {t.name}
                </option>
              ))}
            </select>
          </div>

          {selectedTemplateId ? (
            <Input
              label="مقدار متغیر {customer_name}"
              value={customerNameVar}
              onChange={(e) => setCustomerNameVar(e.target.value)}
            />
          ) : (
            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.messaging.messageBody}
              </label>
              <textarea
                rows={3}
                value={customBody}
                onChange={(e) => setCustomBody(e.target.value)}
                required={!selectedTemplateId}
                className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              />
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setSendOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button
              type="submit"
              isLoading={sendMutation.isPending}
              icon={<CheckCircle2 className="h-4 w-4" />}
            >
              ارسال آنی پیامک
            </Button>
          </div>
        </form>
      </Modal>

      {/* Create Template Modal */}
      <Modal
        isOpen={tplOpen}
        onClose={() => setTplOpen(false)}
        title={fa.messaging.newTemplate}
        description={fa.messaging.variablesHint}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            createTplMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label="عنوان قالب"
            value={tplName}
            onChange={(e) => setTplName(e.target.value)}
            placeholder="مثال: یادآوری نوبت کلینیک"
            required
          />

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              دسته‌بندی قالب
            </label>
            <select
              value={tplCategory}
              onChange={(e: any) => setTplCategory(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              <option value="appointment">نوبت‌دهی (appointment)</option>
              <option value="invoice">مالی و فاکتور (invoice)</option>
              <option value="campaign">کمپین (campaign)</option>
              <option value="custom">عمومی (custom)</option>
            </select>
          </div>

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              متن قالب همراه با متغیرها
            </label>
            <textarea
              rows={3}
              value={tplBody}
              onChange={(e) => setTplBody(e.target.value)}
              required
              className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setTplOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={createTplMutation.isPending}>
              {fa.common.save}
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
