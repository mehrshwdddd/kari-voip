import React, { useState } from 'react'
import {
  Code2,
  Key,
  Webhook as WebhookIcon,
  ShieldCheck,
  Activity,
  Plus,
  Copy,
  Check,
  Send,
  History,
  AlertTriangle,
  Server,
  Database,
  Cpu,
  HardDrive,
  RefreshCw,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Badge } from '../../../components/ui/Badge'
import { DeleteButton } from '../../../components/devigner/DeleteButton'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type {
  ApiToken,
  Webhook,
  WebhookDelivery,
  AuditLogItem,
  SystemStatusData,
} from '../../../types'

export const DeveloperPage: React.FC = () => {
  const queryClient = useQueryClient()
  const [activeTab, setActiveTab] = useState<'tokens' | 'webhooks' | 'audit' | 'health'>('tokens')

  // API Token State
  const [isTokenModalOpen, setIsTokenModalOpen] = useState(false)
  const [tokenName, setTokenName] = useState('')
  const [selectedAbilities, setSelectedAbilities] = useState<string[]>(['*'])
  const [createdPlainTextToken, setCreatedPlainTextToken] = useState<string | null>(null)
  const [isCopied, setIsCopied] = useState(false)

  // Webhook State
  const [isWebhookModalOpen, setIsWebhookModalOpen] = useState(false)
  const [webhookName, setWebhookName] = useState('')
  const [webhookUrl, setWebhookUrl] = useState('')
  const [webhookSecret, setWebhookSecret] = useState('')
  const [webhookEvents, setWebhookEvents] = useState<string[]>(['*'])
  const [selectedWebhookForDeliveries, setSelectedWebhookForDeliveries] = useState<Webhook | null>(null)
  const [deliveryTestStatus, setDeliveryTestStatus] = useState<string | null>(null)

  // Audit Logs State
  const [auditEventType, setAuditEventType] = useState('')

  // 1. Queries
  const { data: tokensData, isLoading: isTokensLoading } = useQuery<ApiToken[]>({
    queryKey: ['developer-tokens'],
    queryFn: async () => {
      const res = await apiClient.get('/developer/tokens')
      return res.data.data
    },
    enabled: activeTab === 'tokens',
  })

  const { data: webhooksData, isLoading: isWebhooksLoading } = useQuery<Webhook[]>({
    queryKey: ['developer-webhooks'],
    queryFn: async () => {
      const res = await apiClient.get('/developer/webhooks')
      return res.data.data
    },
    enabled: activeTab === 'webhooks',
  })

  const { data: deliveriesData, isLoading: isDeliveriesLoading } = useQuery<{ data: WebhookDelivery[] }>({
    queryKey: ['developer-webhook-deliveries', selectedWebhookForDeliveries?.id],
    queryFn: async () => {
      if (!selectedWebhookForDeliveries) return { data: [] }
      const res = await apiClient.get(`/developer/webhooks/${selectedWebhookForDeliveries.id}/deliveries`)
      return res.data.data
    },
    enabled: !!selectedWebhookForDeliveries,
  })

  const { data: auditLogsData, isLoading: isAuditLoading } = useQuery<{ data: AuditLogItem[] }>({
    queryKey: ['developer-audit-logs', auditEventType],
    queryFn: async () => {
      const params = auditEventType ? `?event_type=${auditEventType}` : ''
      const res = await apiClient.get(`/developer/audit-logs${params}`)
      return res.data.data
    },
    enabled: activeTab === 'audit',
  })

  const { data: systemStatusData, isLoading: isStatusLoading, refetch: refetchStatus } = useQuery<SystemStatusData>({
    queryKey: ['developer-system-status'],
    queryFn: async () => {
      const res = await apiClient.get('/developer/system-status')
      return res.data.data
    },
    enabled: activeTab === 'health',
  })

  // 2. Mutations
  const createTokenMutation = useMutation({
    mutationFn: async (payload: { name: string; abilities: string[] }) => {
      const res = await apiClient.post('/developer/tokens', payload)
      return res.data.data
    },
    onSuccess: (data) => {
      setCreatedPlainTextToken(data.plain_text_token)
      queryClient.invalidateQueries({ queryKey: ['developer-tokens'] })
      setTokenName('')
    },
  })

  const revokeTokenMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/developer/tokens/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['developer-tokens'] })
    },
  })

  const createWebhookMutation = useMutation({
    mutationFn: async (payload: { name: string; url: string; secret?: string; events: string[] }) => {
      const res = await apiClient.post('/developer/webhooks', payload)
      return res.data.data
    },
    onSuccess: () => {
      setIsWebhookModalOpen(false)
      queryClient.invalidateQueries({ queryKey: ['developer-webhooks'] })
      setWebhookName('')
      setWebhookUrl('')
      setWebhookSecret('')
    },
  })

  const deleteWebhookMutation = useMutation({
    mutationFn: async (id: number) => {
      await apiClient.delete(`/developer/webhooks/${id}`)
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['developer-webhooks'] })
      if (selectedWebhookForDeliveries) {
        setSelectedWebhookForDeliveries(null)
      }
    },
  })

  const testWebhookMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/developer/webhooks/${id}/test`)
      return res.data.data
    },
    onSuccess: (data) => {
      setDeliveryTestStatus(`پیام تست ارسال شد (کد پاسخ: ${data.response_status || 'نامشخص'})`)
      queryClient.invalidateQueries({ queryKey: ['developer-webhooks'] })
      if (selectedWebhookForDeliveries) {
        queryClient.invalidateQueries({ queryKey: ['developer-webhook-deliveries', selectedWebhookForDeliveries.id] })
      }
      setTimeout(() => setDeliveryTestStatus(null), 4000)
    },
  })

  const copyToClipboard = (text: string) => {
    navigator.clipboard.writeText(text)
    setIsCopied(true)
    setTimeout(() => setIsCopied(false), 3000)
  }

  const availableAbilities = [
    { key: '*', label: 'دسترسی کامل (Full Access)' },
    { key: 'calls:read', label: 'مشاهده تماس‌ها و رخدادها' },
    { key: 'calls:write', label: 'ثبت تماس و تغییر وضعیت' },
    { key: 'contacts:read', label: 'مشاهده اطلاعات مخاطبان CRM' },
    { key: 'contacts:write', label: 'ثبت و ویرایش مخاطبان' },
    { key: 'appointments:manage', label: 'مدیریت و رزرو نوبت‌ها' },
    { key: 'invoices:manage', label: 'مدیریت و صدور فاکتورها' },
    { key: 'webhooks:manage', label: 'مدیریت وب‌هوک‌ها' },
  ]

  const availableEvents = [
    { key: '*', label: 'همه رویدادها (*)' },
    { key: 'call.started', label: 'شروع تماس جدید' },
    { key: 'call.ended', label: 'پایان مکالمه' },
    { key: 'appointment.created', label: 'ثبت نوبت جدید' },
    { key: 'invoice.paid', label: 'پرداخت فاکتور' },
    { key: 'contact.created', label: 'ایجاد مخاطب جدید' },
  ]

  return (
    <div className="space-y-6" dir="rtl">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <div className="p-2 bg-emerald-500/10 rounded-xl text-emerald-600 dark:text-emerald-400">
              <Code2 className="w-6 h-6" />
            </div>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
              {fa.developer.title}
            </h1>
          </div>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {fa.developer.subtitle}
          </p>
        </div>

        {/* Tab Navigation */}
        <div className="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
          <button
            onClick={() => setActiveTab('tokens')}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              activeTab === 'tokens'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <Key className="w-4 h-4" />
            {fa.developer.apiTokens}
          </button>
          <button
            onClick={() => setActiveTab('webhooks')}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              activeTab === 'webhooks'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <WebhookIcon className="w-4 h-4" />
            {fa.developer.webhooks}
          </button>
          <button
            onClick={() => setActiveTab('audit')}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              activeTab === 'audit'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <ShieldCheck className="w-4 h-4" />
            {fa.developer.auditLogs}
          </button>
          <button
            onClick={() => setActiveTab('health')}
            className={`flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors ${
              activeTab === 'health'
                ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-xs'
                : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'
            }`}
          >
            <Activity className="w-4 h-4" />
            {fa.developer.systemHealth}
          </button>
        </div>
      </div>

      {deliveryTestStatus && (
        <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-sm text-emerald-800 dark:text-emerald-300 flex items-center justify-between">
          <span>{deliveryTestStatus}</span>
          <Check className="w-4 h-4 text-emerald-600" />
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 1: API TOKENS */}
      {/* ========================================================================= */}
      {activeTab === 'tokens' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              کلیدهای احراز هویت Sanctum (Bearer Token)
            </h2>
            <Button
              onClick={() => {
                setCreatedPlainTextToken(null)
                setIsTokenModalOpen(true)
              }}
              className="flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              {fa.developer.newToken}
            </Button>
          </div>

          <Card className="divide-y divide-gray-100 dark:divide-gray-800">
            {isTokensLoading ? (
              <div className="p-8 text-center text-gray-500">{fa.common.loading}</div>
            ) : !tokensData || tokensData.length === 0 ? (
              <div className="p-8 text-center text-gray-500">
                هیچ کلید API تعریف نشده است. برای اتصال سامانه به نرم‌افزارهای خارجی کلید جدید بسازید.
              </div>
            ) : (
              tokensData.map((token) => (
                <div key={token.id} className="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                  <div className="space-y-1">
                    <div className="flex items-center gap-2">
                      <Key className="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                      <span className="font-semibold text-gray-900 dark:text-white">{token.name}</span>
                      <Badge variant="neutral">شناسه #{toPersianDigits(token.id)}</Badge>
                    </div>
                    <div className="flex flex-wrap gap-1.5 pt-1">
                      {token.abilities.map((ability) => (
                        <span
                          key={ability}
                          className="px-2 py-0.5 text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 rounded-md font-mono"
                        >
                          {ability}
                        </span>
                      ))}
                    </div>
                    <div className="text-xs text-gray-400 pt-1">
                      ایجاد شده: {formatJalali(token.created_at)}
                      {token.last_used_at && ` • آخرین استفاده: ${formatJalali(token.last_used_at)}`}
                    </div>
                  </div>

                  <div>
                    <DeleteButton
                      onConfirm={() => revokeTokenMutation.mutate(token.id)}
                      label={fa.developer.revokeToken}
                      size="sm"
                    />
                  </div>
                </div>
              ))
            )}
          </Card>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: WEBHOOKS */}
      {/* ========================================================================= */}
      {activeTab === 'webhooks' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
              وب‌هوک‌های امن رویدادمحور (Webhook Dispatcher)
            </h2>
            <Button
              onClick={() => setIsWebhookModalOpen(true)}
              className="flex items-center gap-2"
            >
              <Plus className="w-4 h-4" />
              {fa.developer.newWebhook}
            </Button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Webhook List */}
            <div className="lg:col-span-2 space-y-3">
              <Card className="divide-y divide-gray-100 dark:divide-gray-800">
                {isWebhooksLoading ? (
                  <div className="p-8 text-center text-gray-500">{fa.common.loading}</div>
                ) : !webhooksData || webhooksData.length === 0 ? (
                  <div className="p-8 text-center text-gray-500">
                    هیچ وب‌هوکی ثبت نشده است. برای ارسال اطلاعات تماس‌ها و پرداخت‌ها به سایر سامانه‌ها یک وب‌هوک ایجاد کنید.
                  </div>
                ) : (
                  webhooksData.map((hook) => (
                    <div
                      key={hook.id}
                      className={`p-4 transition-colors ${
                        selectedWebhookForDeliveries?.id === hook.id
                          ? 'bg-emerald-50/50 dark:bg-emerald-950/20'
                          : 'hover:bg-gray-50 dark:hover:bg-gray-800/50'
                      }`}
                    >
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div className="space-y-1">
                          <div className="flex items-center gap-2">
                            <span className="font-semibold text-gray-900 dark:text-white">{hook.name}</span>
                            <Badge variant={hook.is_active ? 'success' : 'neutral'}>
                              {hook.is_active ? 'فعال' : 'غیرفعال'}
                            </Badge>
                            {hook.last_status_code && (
                              <Badge variant={hook.last_status_code < 400 ? 'success' : 'danger'}>
                                HTTP {hook.last_status_code}
                              </Badge>
                            )}
                          </div>
                          <p className="text-xs font-mono text-gray-500 dark:text-gray-400 dir-ltr text-right break-all">
                            {hook.url}
                          </p>
                          <div className="flex flex-wrap gap-1 pt-1">
                            {hook.events.map((ev) => (
                              <span
                                key={ev}
                                className="px-1.5 py-0.5 text-xs bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 rounded font-mono"
                              >
                                {ev}
                              </span>
                            ))}
                          </div>
                        </div>

                        <div className="flex items-center gap-2 self-end sm:self-auto">
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => testWebhookMutation.mutate(hook.id)}
                            disabled={testWebhookMutation.isPending}
                            className="flex items-center gap-1.5 text-xs"
                          >
                            <Send className="w-3.5 h-3.5" />
                            {fa.developer.testWebhook}
                          </Button>
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setSelectedWebhookForDeliveries(hook)}
                            className="flex items-center gap-1.5 text-xs"
                          >
                            <History className="w-3.5 h-3.5" />
                            {fa.developer.deliveries}
                          </Button>
                          <DeleteButton
                            onConfirm={() => deleteWebhookMutation.mutate(hook.id)}
                            label="حذف"
                            size="sm"
                          />
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </Card>
            </div>

            {/* Delivery Logs Panel */}
            <div className="lg:col-span-1">
              <Card className="p-4 space-y-3">
                <div className="flex items-center justify-between border-b pb-2 dark:border-gray-800">
                  <h3 className="font-semibold text-sm text-gray-900 dark:text-white flex items-center gap-2">
                    <History className="w-4 h-4 text-emerald-600" />
                    تاریخچه ارسال رویدادها
                  </h3>
                  {selectedWebhookForDeliveries && (
                    <Badge variant="neutral">{selectedWebhookForDeliveries.name}</Badge>
                  )}
                </div>

                {!selectedWebhookForDeliveries ? (
                  <div className="py-12 text-center text-xs text-gray-400">
                    روی دکمه "تاریخچه ارسال‌ها" در یک وب‌هوک کلیک کنید تا سوابق تحویل نمایش یابد.
                  </div>
                ) : isDeliveriesLoading ? (
                  <div className="py-8 text-center text-xs text-gray-500">{fa.common.loading}</div>
                ) : !deliveriesData?.data || deliveriesData.data.length === 0 ? (
                  <div className="py-8 text-center text-xs text-gray-400">
                    هیچ رویدادی تاکنون به این وب‌هوک مخابره نشده است.
                  </div>
                ) : (
                  <div className="space-y-2 max-h-[480px] overflow-y-auto pr-1">
                    {deliveriesData.data.map((del) => (
                      <div
                        key={del.id}
                        className="p-2.5 rounded-lg border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/40 text-xs space-y-1"
                      >
                        <div className="flex items-center justify-between">
                          <span className="font-mono font-bold text-gray-800 dark:text-gray-200">
                            {del.event_type}
                          </span>
                          <span
                            className={`px-1.5 py-0.5 rounded font-mono font-bold ${
                              del.response_status && del.response_status < 400
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'
                            }`}
                          >
                            {del.response_status || 'ERROR'}
                          </span>
                        </div>
                        <div className="flex items-center justify-between text-gray-400">
                          <span>زمان پاسخ: {toPersianDigits(del.duration_ms || 0)} میلی‌ثانیه</span>
                          <span>{formatJalali(del.created_at)}</span>
                        </div>
                        {del.response_body && (
                          <pre className="p-1.5 bg-black/5 dark:bg-black/30 rounded text-[10px] font-mono text-gray-600 dark:text-gray-300 overflow-x-auto max-h-16">
                            {del.response_body}
                          </pre>
                        )}
                      </div>
                    ))}
                  </div>
                )}
              </Card>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: AUDIT LOGS */}
      {/* ========================================================================= */}
      {activeTab === 'audit' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                لاگ‌های ممیزی و امنیت (Audit Trail)
              </h2>
              <p className="text-xs text-gray-500">
                کلیه عملیات حساس، ورود کاربران، ویرایش تنظیمات و انتقال تماس‌ها با جزئیات کامل ذخیره می‌شوند.
              </p>
            </div>

            <div className="flex items-center gap-2">
              <input
                type="text"
                placeholder="فیلتر بر اساس نام رویداد (مثلاً call_transfer)..."
                value={auditEventType}
                onChange={(e) => setAuditEventType(e.target.value)}
                className="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white w-64"
              />
            </div>
          </div>

          <Card className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-gray-50 dark:bg-gray-800/60 text-gray-600 dark:text-gray-300 border-b dark:border-gray-800">
                <tr>
                  <th className="p-3">کاربر</th>
                  <th className="p-3">نوع رخداد</th>
                  <th className="p-3">مدل مربوطه</th>
                  <th className="p-3">آدرس IP</th>
                  <th className="p-3">تاریخ و ساعت</th>
                  <th className="p-3">جزئیات تغییرات</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                {isAuditLoading ? (
                  <tr>
                    <td colSpan={6} className="p-8 text-center text-gray-500">
                      {fa.common.loading}
                    </td>
                  </tr>
                ) : !auditLogsData?.data || auditLogsData.data.length === 0 ? (
                  <tr>
                    <td colSpan={6} className="p-8 text-center text-gray-500">
                      هیچ لاگ ممیزی مطابق فیلتر یافت نشد.
                    </td>
                  </tr>
                ) : (
                  auditLogsData.data.map((log) => (
                    <tr key={log.id} className="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                      <td className="p-3 font-medium text-gray-900 dark:text-white">
                        {log.user ? `${log.user.name} (${log.user.email})` : 'سیستم خودکار'}
                      </td>
                      <td className="p-3 font-mono">
                        <Badge variant="neutral">{log.event_type}</Badge>
                      </td>
                      <td className="p-3 font-mono text-gray-600 dark:text-gray-400">
                        {log.model_type ? log.model_type.split('\\').pop() : '-'}
                        {log.model_id && ` #${toPersianDigits(log.model_id)}`}
                      </td>
                      <td className="p-3 font-mono text-gray-500 dir-ltr text-right">
                        {log.ip_address || '-'}
                      </td>
                      <td className="p-3 text-gray-500">{formatJalali(log.created_at)}</td>
                      <td className="p-3 max-w-xs truncate font-mono text-[10px] text-gray-500">
                        {log.new_values ? JSON.stringify(log.new_values) : '-'}
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </Card>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 4: SYSTEM HEALTH */}
      {/* ========================================================================= */}
      {activeTab === 'health' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center">
            <div>
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                پایش زیرساخت فنی و سلامت سرویس‌ها
              </h2>
              <p className="text-xs text-gray-500">
                بررسی بلادرنگ پایگاه‌داده، صفوف پردازشی، استوریج فایل و ارتباطات
              </p>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={() => refetchStatus()}
              className="flex items-center gap-1.5"
            >
              <RefreshCw className="w-3.5 h-3.5" />
              بررسی مجدد
            </Button>
          </div>

          {isStatusLoading || !systemStatusData ? (
            <div className="p-12 text-center text-gray-500">{fa.common.loading}</div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              <Card className="p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500">پایگاه داده</span>
                  <Database className="w-4 h-4 text-emerald-600" />
                </div>
                <div className="text-lg font-bold text-gray-900 dark:text-white">
                  {systemStatusData.database.connection}
                </div>
                <Badge variant={systemStatusData.database.connected ? 'success' : 'danger'}>
                  {systemStatusData.database.connected ? 'متصل و آماده' : 'خطای ارتباط'}
                </Badge>
              </Card>

              <Card className="p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500">محیط و نسخه هسته</span>
                  <Server className="w-4 h-4 text-blue-600" />
                </div>
                <div className="text-lg font-bold text-gray-900 dark:text-white">
                  Laravel {systemStatusData.laravel_version}
                </div>
                <div className="text-xs text-gray-500 font-mono">
                  PHP {systemStatusData.php_version} ({systemStatusData.environment})
                </div>
              </Card>

              <Card className="p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500">درایور صف و کش</span>
                  <Cpu className="w-4 h-4 text-purple-600" />
                </div>
                <div className="text-lg font-bold text-gray-900 dark:text-white">
                  {systemStatusData.queue.driver} / {systemStatusData.cache.driver}
                </div>
                <span className="text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                  آماده دریافت صفوف مکالمات
                </span>
              </Card>

              <Card className="p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-xs text-gray-500">دسترسی دیسک ضبط صدا</span>
                  <HardDrive className="w-4 h-4 text-amber-600" />
                </div>
                <div className="text-lg font-bold text-gray-900 dark:text-white">
                  {systemStatusData.storage.writable ? 'مجاز به ذخیره‌سازی' : 'دسترسی محدود'}
                </div>
                <Badge variant={systemStatusData.storage.writable ? 'success' : 'danger'}>
                  {systemStatusData.storage.writable ? 'Writable OK' : 'Read Only'}
                </Badge>
              </Card>
            </div>
          )}
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL: CREATE API TOKEN */}
      {/* ========================================================================= */}
      {isTokenModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
          <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-lg shadow-xl space-y-4" dir="rtl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <Key className="w-5 h-5 text-emerald-600" />
              {fa.developer.newToken}
            </h3>

            {createdPlainTextToken ? (
              <div className="space-y-4">
                <div className="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 rounded-xl text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                  <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                  <span>{fa.developer.tokenCreatedWarning}</span>
                </div>

                <div className="p-3 bg-gray-50 dark:bg-gray-900 border rounded-xl font-mono text-xs text-gray-800 dark:text-gray-200 break-all dir-ltr select-all">
                  {createdPlainTextToken}
                </div>

                <div className="flex gap-2">
                  <Button
                    onClick={() => copyToClipboard(createdPlainTextToken)}
                    className="flex-1 flex items-center justify-center gap-2"
                  >
                    {isCopied ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                    {isCopied ? fa.developer.copied : fa.developer.copyToken}
                  </Button>
                  <Button
                    variant="outline"
                    onClick={() => setIsTokenModalOpen(false)}
                  >
                    بستن
                  </Button>
                </div>
              </div>
            ) : (
              <div className="space-y-4">
                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {fa.developer.tokenName}
                  </label>
                  <input
                    type="text"
                    required
                    placeholder="مثلاً: کلید اتصال نرم‌افزار نوبت‌دهی وب‌سایت"
                    value={tokenName}
                    onChange={(e) => setTokenName(e.target.value)}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                  />
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                    {fa.developer.abilities}
                  </label>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {availableAbilities.map((ab) => (
                      <label
                        key={ab.key}
                        className="flex items-center gap-2 p-2 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50 cursor-pointer text-xs"
                      >
                        <input
                          type="checkbox"
                          checked={selectedAbilities.includes(ab.key)}
                          onChange={(e) => {
                            if (e.target.checked) {
                              setSelectedAbilities([...selectedAbilities, ab.key])
                            } else {
                              setSelectedAbilities(selectedAbilities.filter((k) => k !== ab.key))
                            }
                          }}
                          className="rounded text-emerald-600 focus:ring-emerald-500"
                        />
                        <span className="text-gray-700 dark:text-gray-300">{ab.label}</span>
                      </label>
                    ))}
                  </div>
                </div>

                <div className="flex justify-end gap-2 pt-2">
                  <Button
                    variant="outline"
                    onClick={() => setIsTokenModalOpen(false)}
                  >
                    {fa.common.cancel}
                  </Button>
                  <Button
                    onClick={() => createTokenMutation.mutate({ name: tokenName, abilities: selectedAbilities })}
                    disabled={!tokenName || createTokenMutation.isPending}
                  >
                    تولید و دریافت کلید
                  </Button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL: CREATE WEBHOOK */}
      {/* ========================================================================= */}
      {isWebhookModalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
          <div className="bg-white dark:bg-gray-800 rounded-2xl p-6 w-full max-w-lg shadow-xl space-y-4" dir="rtl">
            <h3 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
              <WebhookIcon className="w-5 h-5 text-emerald-600" />
              {fa.developer.newWebhook}
            </h3>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.developer.webhookName}
                </label>
                <input
                  type="text"
                  required
                  placeholder="مثلاً: سرویس حسابداری آنلاین"
                  value={webhookName}
                  onChange={(e) => setWebhookName(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.developer.webhookUrl}
                </label>
                <input
                  type="url"
                  required
                  placeholder="https://example.com/api/webhooks/voicereach"
                  value={webhookUrl}
                  onChange={(e) => setWebhookUrl(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.developer.signingSecret} (اختیاری)
                </label>
                <input
                  type="text"
                  placeholder="در صورت خالی ماندن، کلید امن ۳۲ کاراکتری تولید خواهد شد"
                  value={webhookSecret}
                  onChange={(e) => setWebhookSecret(e.target.value)}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                  {fa.developer.subscribedEvents}
                </label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {availableEvents.map((ev) => (
                    <label
                      key={ev.key}
                      className="flex items-center gap-2 p-2 rounded-lg border border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-900/50 cursor-pointer text-xs"
                    >
                      <input
                        type="checkbox"
                        checked={webhookEvents.includes(ev.key)}
                        onChange={(e) => {
                          if (e.target.checked) {
                            setWebhookEvents([...webhookEvents, ev.key])
                          } else {
                            setWebhookEvents(webhookEvents.filter((k) => k !== ev.key))
                          }
                        }}
                        className="rounded text-emerald-600 focus:ring-emerald-500"
                      />
                      <span className="text-gray-700 dark:text-gray-300">{ev.label}</span>
                    </label>
                  ))}
                </div>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <Button
                  variant="outline"
                  onClick={() => setIsWebhookModalOpen(false)}
                >
                  {fa.common.cancel}
                </Button>
                <Button
                  onClick={() =>
                    createWebhookMutation.mutate({
                      name: webhookName,
                      url: webhookUrl,
                      secret: webhookSecret || undefined,
                      events: webhookEvents,
                    })
                  }
                  disabled={!webhookName || !webhookUrl || createWebhookMutation.isPending}
                >
                  ثبت وب‌هوک
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
export default DeveloperPage
