import React, { useState } from 'react'
import { useParams, useNavigate } from 'react-router-dom'
import {
  ArrowRight,
  Play,
  Pause,
  XCircle,
  PhoneOutgoing,
  Download,
  Users,
  CheckCircle2,
  ShieldAlert,
  Headphones,
  Volume2,
  Hash,
} from 'lucide-react'
import {
  ResponsiveContainer,
  BarChart,
  Bar,
  XAxis,
  YAxis,
  Tooltip,
  CartesianGrid,
} from 'recharts'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { EmptyState } from '../../../components/ui/EmptyState'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type { CampaignReport, CampaignCallLog, PaginatedResponse } from '../../../types'

export const CampaignDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [testCallOpen, setTestCallOpen] = useState(false)
  const [testPhone, setTestPhone] = useState('09121112233')
  const [feedbackMsg, setFeedbackMsg] = useState<string | null>(null)
  const [errorMsg, setErrorMsg] = useState<string | null>(null)

  const { data: reportData, isLoading } = useQuery<CampaignReport>({
    queryKey: ['campaign-report', id],
    queryFn: async () => {
      const res = await apiClient.get(`/campaigns/${id}/report`)
      return res.data
    },
    refetchInterval: 4000,
  })

  const { data: logsData } = useQuery<PaginatedResponse<CampaignCallLog>>({
    queryKey: ['campaign-logs', id],
    queryFn: async () => {
      const res = await apiClient.get(`/campaigns/${id}/logs`)
      return res.data
    },
    refetchInterval: 4000,
  })

  const refreshData = () => {
    queryClient.invalidateQueries({ queryKey: ['campaign-report', id] })
    queryClient.invalidateQueries({ queryKey: ['campaign-logs', id] })
    queryClient.invalidateQueries({ queryKey: ['campaigns-list'] })
  }

  const controlMutation = useMutation({
    mutationFn: async (action: 'start' | 'pause' | 'resume' | 'cancel') => {
      const res = await apiClient.post(`/campaigns/${id}/${action}`)
      return res.data
    },
    onSuccess: (res) => {
      setFeedbackMsg(res.message)
      setErrorMsg(null)
      refreshData()
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || fa.common.error)
    },
  })

  const testCallMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post(`/campaigns/${id}/test-call`, {
        phone_number: testPhone,
      })
      return res.data
    },
    onSuccess: (res) => {
      setFeedbackMsg(res.message)
      setErrorMsg(null)
      setTestCallOpen(false)
    },
    onError: (err: any) => {
      setErrorMsg(err.response?.data?.message || fa.common.error)
      setTestCallOpen(false)
    },
  })

  const handleExportCsv = async () => {
    try {
      const res = await apiClient.get(`/campaigns/${id}/export`, {
        responseType: 'blob',
      })
      const url = window.URL.createObjectURL(new Blob([res.data]))
      const link = document.createElement('a')
      link.href = url
      link.setAttribute('download', `campaign-${id}-report.csv`)
      document.body.appendChild(link)
      link.click()
      link.remove()
    } catch {
      setErrorMsg('خطا در دریافت فایل خروجی گزارش کمپین.')
    }
  }

  if (isLoading) {
    return (
      <div className="p-12 text-center text-xs text-slate-400">
        {fa.common.loading}
      </div>
    )
  }

  const campaign = reportData?.campaign
  const summary = reportData?.summary
  const chartData = reportData?.outcomes_chart || []
  const logs = logsData?.data || []

  if (!campaign || !summary) {
    return (
      <EmptyState
        title="کمپین یافت نشد"
        actionLabel="بازگشت به لیست کمپین‌ها"
        onAction={() => navigate('/campaigns')}
      />
    )
  }

  return (
    <div className="space-y-6">
      {/* Top Bar */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <button
          type="button"
          onClick={() => navigate('/campaigns')}
          className="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 transition-colors cursor-pointer"
        >
          <ArrowRight className="h-4 w-4" />
          <span>بازگشت به لیست کمپین‌ها</span>
        </button>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            icon={<PhoneOutgoing className="h-3.5 w-3.5 text-blue-600" />}
            onClick={() => setTestCallOpen(true)}
          >
            {fa.campaigns.testCallButton}
          </Button>
          <Button
            variant="outline"
            size="sm"
            icon={<Download className="h-3.5 w-3.5 text-emerald-600" />}
            onClick={handleExportCsv}
          >
            {fa.campaigns.exportExcel}
          </Button>
        </div>
      </div>

      {feedbackMsg && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300">
          {feedbackMsg}
        </div>
      )}

      {errorMsg && (
        <div className="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs font-bold text-rose-600 dark:text-rose-400">
          {errorMsg}
        </div>
      )}

      {/* Campaign Header & Live Controls */}
      <Card className="p-6">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div>
            <div className="flex items-center gap-3">
              <h1 className="text-lg font-black text-slate-900 dark:text-slate-100">
                {campaign.name}
              </h1>
              <Badge
                variant={
                  campaign.status === 'running'
                    ? 'success'
                    : campaign.status === 'completed'
                    ? 'primary'
                    : campaign.status === 'paused'
                    ? 'warning'
                    : 'neutral'
                }
              >
                {campaign.status === 'running'
                  ? fa.campaigns.statusRunning
                  : campaign.status === 'completed'
                  ? fa.campaigns.statusCompleted
                  : campaign.status === 'paused'
                  ? fa.campaigns.statusPaused
                  : campaign.status === 'cancelled'
                  ? fa.campaigns.statusCancelled
                  : fa.campaigns.statusDraft}
              </Badge>
            </div>
            {campaign.description && (
              <p className="text-xs text-slate-500 mt-1.5">{campaign.description}</p>
            )}
            <div className="flex flex-wrap items-center gap-4 text-[11px] text-slate-400 mt-3">
              <span>
                بازه مجاز: {toPersianDigits(campaign.allowed_hours_start)} تا{' '}
                {toPersianDigits(campaign.allowed_hours_end)}
              </span>
              <span>
                کانال همزمان: {toPersianDigits(campaign.max_concurrent_calls)}
              </span>
              <span>
                تلاش مجدد (Retry): حداکثر {toPersianDigits(campaign.max_retry_count)} بار
              </span>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {(campaign.status === 'draft' || campaign.status === 'scheduled') && (
              <Button
                size="md"
                className="bg-emerald-600 hover:bg-emerald-700"
                icon={<Play className="h-4 w-4" />}
                isLoading={controlMutation.isPending}
                onClick={() => controlMutation.mutate('start')}
              >
                {fa.campaigns.startCampaign}
              </Button>
            )}

            {campaign.status === 'running' && (
              <Button
                size="md"
                variant="secondary"
                icon={<Pause className="h-4 w-4" />}
                isLoading={controlMutation.isPending}
                onClick={() => controlMutation.mutate('pause')}
              >
                {fa.campaigns.pauseCampaign}
              </Button>
            )}

            {campaign.status === 'paused' && (
              <Button
                size="md"
                icon={<Play className="h-4 w-4" />}
                isLoading={controlMutation.isPending}
                onClick={() => controlMutation.mutate('resume')}
              >
                {fa.campaigns.resumeCampaign}
              </Button>
            )}

            {campaign.status !== 'completed' && campaign.status !== 'cancelled' && (
              <Button
                size="md"
                variant="danger"
                icon={<XCircle className="h-4 w-4" />}
                isLoading={controlMutation.isPending}
                onClick={() => controlMutation.mutate('cancel')}
              >
                {fa.campaigns.cancelCampaign}
              </Button>
            )}
          </div>
        </div>

        {/* Live Progress Bar */}
        <div className="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800">
          <div className="flex items-center justify-between text-xs font-bold mb-2">
            <span className="text-slate-600 dark:text-slate-300">
              {fa.campaigns.progressLabel} ({toPersianDigits(summary.completed_contacts)} از{' '}
              {toPersianDigits(summary.total_contacts)} مخاطب)
            </span>
            <span className="text-blue-600">
              %{toPersianDigits(campaign.progress_percentage)}
            </span>
          </div>
          <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
            <div
              className="h-full bg-gradient-to-l from-blue-600 to-emerald-500 transition-all duration-500"
              style={{ width: `${campaign.progress_percentage}%` }}
            />
          </div>
        </div>
      </Card>

      {/* KPI Metrics Row */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <Users className="h-3.5 w-3.5 text-blue-500" />
            {fa.campaigns.totalTargets}
          </span>
          <div className="text-xl font-black text-slate-900 dark:text-slate-100 mt-1">
            {toPersianDigits(summary.total_contacts)}
          </div>
        </Card>

        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <CheckCircle2 className="h-3.5 w-3.5 text-emerald-500" />
            {fa.campaigns.successfulCalls}
          </span>
          <div className="text-xl font-black text-emerald-600 mt-1">
            {toPersianDigits(summary.success_count)}
          </div>
        </Card>

        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <Volume2 className="h-3.5 w-3.5 text-amber-500" />
            {fa.campaigns.avgListenRate}
          </span>
          <div className="text-xl font-black text-amber-600 mt-1">
            %{toPersianDigits(summary.avg_listen_percentage)}
          </div>
        </Card>

        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <Hash className="h-3.5 w-3.5 text-indigo-500" />
            {fa.campaigns.dtmfInteractions}
          </span>
          <div className="text-xl font-black text-indigo-600 mt-1">
            {toPersianDigits(summary.dtmf_interactions)}
          </div>
        </Card>

        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <Headphones className="h-3.5 w-3.5 text-blue-500" />
            {fa.campaigns.humanTransfers}
          </span>
          <div className="text-xl font-black text-blue-600 mt-1">
            {toPersianDigits(summary.human_transfers)}
          </div>
        </Card>

        <Card className="p-4">
          <span className="text-[11px] text-slate-400 flex items-center gap-1">
            <ShieldAlert className="h-3.5 w-3.5 text-rose-500" />
            ردشده DNC
          </span>
          <div className="text-xl font-black text-rose-600 mt-1">
            {toPersianDigits(summary.skipped_dnc_count)}
          </div>
        </Card>
      </div>

      {/* Recharts Outcome Distribution Chart */}
      <Card className="p-6">
        <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 mb-4">
          {fa.campaigns.outcomeChartTitle}
        </h3>
        <div className="h-64 w-full" dir="ltr">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={chartData}>
              <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
              <XAxis dataKey="label" tick={{ fontSize: 11 }} />
              <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
              <Tooltip />
              <Bar dataKey="count" fill="#2563EB" radius={[8, 8, 0, 0]} />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </Card>

      {/* Detailed Audit Call Logs Table */}
      <Card className="p-0 overflow-hidden">
        <div className="p-4 border-b border-slate-100 dark:border-slate-800">
          <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
            {fa.campaigns.logsTableTitle}
          </h3>
        </div>

        {logs.length === 0 ? (
          <div className="p-8 text-center text-xs text-slate-400">
            هنوز لاگ تماسی برای این کمپین ثبت نشده است. برای شروع روی «شروع اجرای کمپین» کلیک کنید.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-right text-xs">
              <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                <tr>
                  <th className="px-4 py-3.5">شماره تماس / مخاطب</th>
                  <th className="px-4 py-3.5">تلاش</th>
                  <th className="px-4 py-3.5">مدت مکالمه</th>
                  <th className="px-4 py-3.5">درصد شنیدن پیام</th>
                  <th className="px-4 py-3.5">کلید فشرده‌شده (DTMF)</th>
                  <th className="px-4 py-3.5">نتیجه نهایی (Outcome)</th>
                  <th className="px-4 py-3.5">زمان</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {logs.map((log) => (
                  <tr
                    key={log.id}
                    className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors"
                  >
                    <td className="px-4 py-3.5">
                      <div className="font-mono font-bold text-slate-900 dark:text-slate-100">
                        {toPersianDigits(log.phone_number)}
                      </div>
                      {log.contact && (
                        <div className="text-[11px] text-slate-400">
                          {log.contact.first_name} {log.contact.last_name}
                        </div>
                      )}
                    </td>

                    <td className="px-4 py-3.5">
                      تلاش #{toPersianDigits(log.attempt_number)}
                    </td>

                    <td className="px-4 py-3.5 font-mono">
                      {toPersianDigits(log.duration_seconds)} ثانیه
                    </td>

                    <td className="px-4 py-3.5">
                      <div className="flex items-center gap-2">
                        <div className="w-20 h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                          <div
                            className="h-full bg-emerald-500"
                            style={{ width: `${log.listen_percentage}%` }}
                          />
                        </div>
                        <span className="font-mono text-[11px]">
                          %{toPersianDigits(log.listen_percentage)}
                        </span>
                      </div>
                    </td>

                    <td className="px-4 py-3.5">
                      {log.dtmf_input ? (
                        <Badge variant="primary">کلید {toPersianDigits(log.dtmf_input)}</Badge>
                      ) : (
                        <span className="text-slate-400">-</span>
                      )}
                    </td>

                    <td className="px-4 py-3.5">
                      <Badge
                        variant={
                          log.outcome === 'completed' ||
                          log.outcome === 'answered' ||
                          log.outcome === 'human_transfer_requested'
                            ? 'success'
                            : log.outcome === 'opted_out'
                            ? 'danger'
                            : 'warning'
                        }
                      >
                        {log.outcome}
                      </Badge>
                    </td>

                    <td className="px-4 py-3.5 text-[11px] text-slate-500">
                      {formatJalali(log.started_at || log.created_at, true)}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      {/* Single Number Test Call Modal */}
      <Modal
        isOpen={testCallOpen}
        onClose={() => setTestCallOpen(false)}
        title={fa.campaigns.testCallButton}
        description="برقراری تماس آزمایشی با یک شماره جهت تست کیفیت صدا و منوی کلیدمحور پیش از شروع کمپین"
      >
        <form
          onSubmit={(e) => {
            e.preventDefault()
            testCallMutation.mutate()
          }}
          className="space-y-4"
        >
          <Input
            label="شماره تلفن مقصد تست"
            dir="ltr"
            value={testPhone}
            onChange={(e) => setTestPhone(e.target.value)}
            required
          />
          <div className="flex items-center justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setTestCallOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={testCallMutation.isPending}>
              برقراری تماس تستی
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
