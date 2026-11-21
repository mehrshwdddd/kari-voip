import React, { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  BarChart3,
  Download,
  PhoneCall,
  Users,
  Calendar,
  Receipt,
  MessageSquare,
  ShieldAlert,
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
import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Badge } from '../../../components/ui/Badge'
import { ContextMenu } from '../../../components/devigner/ContextMenu'
import { toPersianDigits } from '../../../lib/numerals'
import { formatToman } from '../../../lib/currency'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'
import type {
  ExecutiveReportData,
  UserPerformanceItem,
  Branch,
} from '../../../types'

export const ReportsPage: React.FC = () => {
  const navigate = useNavigate()

  const [selectedBranch, setSelectedBranch] = useState('')
  const [fromDate, setFromDate] = useState('')
  const [toDate, setToDate] = useState('')

  const { data: dashData, isLoading } = useQuery<ExecutiveReportData>({
    queryKey: ['reports-dashboard', selectedBranch, fromDate, toDate],
    queryFn: async () => {
      const params = new URLSearchParams()
      if (selectedBranch) params.append('branch_id', selectedBranch)
      if (fromDate) params.append('from_date', fromDate)
      if (toDate) params.append('to_date', toDate)
      const res = await apiClient.get(`/reports/dashboard?${params.toString()}`)
      return res.data
    },
  })

  const { data: usersReportData } = useQuery<{ users_performance: UserPerformanceItem[] }>({
    queryKey: ['reports-users'],
    queryFn: async () => {
      const res = await apiClient.get('/reports/users')
      return res.data
    },
  })

  const { data: branchesData } = useQuery<{ branches: Branch[] }>({
    queryKey: ['branches-select'],
    queryFn: async () => {
      const res = await apiClient.get('/branches')
      return res.data
    },
  })

  const handleExport = async (moduleName: 'calls' | 'revenue' | 'appointments') => {
    const res = await apiClient.get(`/reports/export?module=${moduleName}`, {
      responseType: 'blob',
    })
    const url = window.URL.createObjectURL(new Blob([res.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `voicereach-${moduleName}-report.csv`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  }

  const kpis = dashData?.kpis
  const charts = dashData?.charts
  const agents = usersReportData?.users_performance || []

  return (
    <div className="space-y-6">
      {/* Header & Excel Export Buttons */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.reports.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.reports.subtitle}
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            icon={<Download className="h-3.5 w-3.5 text-blue-600" />}
            onClick={() => handleExport('calls')}
          >
            {fa.reports.exportCalls}
          </Button>
          <Button
            variant="outline"
            size="sm"
            icon={<Download className="h-3.5 w-3.5 text-emerald-600" />}
            onClick={() => handleExport('revenue')}
          >
            {fa.reports.exportRevenue}
          </Button>
          <Button
            variant="outline"
            size="sm"
            icon={<Download className="h-3.5 w-3.5 text-amber-600" />}
            onClick={() => handleExport('appointments')}
          >
            {fa.reports.exportAppointments}
          </Button>
        </div>
      </div>

      {/* Advanced Filters & Jalali Date Range */}
      <Card className="p-4">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label className="block text-[11px] text-slate-400 mb-1">
              فیلتر شعبه
            </label>
            <select
              value={selectedBranch}
              onChange={(e) => setSelectedBranch(e.target.value)}
              className="w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900"
            >
              <option value="">همه شعب سازمان</option>
              {branchesData?.branches?.map((b) => (
                <option key={b.id} value={b.id}>
                  {b.name}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-[11px] text-slate-400 mb-1">
              {fa.reports.fromDate} {fromDate && `(${formatJalali(fromDate)})`}
            </label>
            <input
              type="date"
              dir="ltr"
              value={fromDate}
              onChange={(e) => setFromDate(e.target.value)}
              className="w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900"
            />
          </div>

          <div>
            <label className="block text-[11px] text-slate-400 mb-1">
              {fa.reports.toDate} {toDate && `(${formatJalali(toDate)})`}
            </label>
            <input
              type="date"
              dir="ltr"
              value={toDate}
              onChange={(e) => setToDate(e.target.value)}
              className="w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-900"
            />
          </div>
        </div>
      </Card>

      {isLoading || !kpis ? (
        <div className="p-12 text-center text-xs text-slate-400">
          {fa.common.loading}
        </div>
      ) : (
        <>
          {/* Executive KPI Cards */}
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <Users className="h-3.5 w-3.5 text-blue-500" />
                کل مخاطبین (VIP: {toPersianDigits(kpis.vip_contacts)})
              </span>
              <div className="text-xl font-black text-slate-900 dark:text-slate-100 mt-1">
                {toPersianDigits(kpis.total_contacts)}
              </div>
            </Card>

            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <PhoneCall className="h-3.5 w-3.5 text-emerald-500" />
                کل تماس‌ها
              </span>
              <div className="text-xl font-black text-emerald-600 mt-1">
                {toPersianDigits(kpis.total_calls)}
              </div>
            </Card>

            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <Calendar className="h-3.5 w-3.5 text-indigo-500" />
                نوبت‌های ثبت‌شده
              </span>
              <div className="text-xl font-black text-indigo-600 mt-1">
                {toPersianDigits(kpis.total_appointments)}
              </div>
            </Card>

            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <Receipt className="h-3.5 w-3.5 text-blue-500" />
                وصولی کل
              </span>
              <div className="text-sm font-black text-blue-600 mt-1.5">
                {formatToman(kpis.total_collected_toman)}
              </div>
            </Card>

            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <MessageSquare className="h-3.5 w-3.5 text-amber-500" />
                پیامک‌های ارسالی
              </span>
              <div className="text-xl font-black text-amber-600 mt-1">
                {toPersianDigits(kpis.total_sms_sent)}
              </div>
            </Card>

            <Card className="p-4">
              <span className="text-[11px] text-slate-400 flex items-center gap-1">
                <ShieldAlert className="h-3.5 w-3.5 text-rose-500" />
                لیست سیاه DNC
              </span>
              <div className="text-xl font-black text-rose-600 mt-1">
                {toPersianDigits(kpis.dnc_count)}
              </div>
            </Card>
          </div>

          {/* Recharts Analytics Grid */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <Card className="p-6">
              <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 mb-4 flex items-center gap-2">
                <BarChart3 className="h-4 w-4 text-blue-600" />
                <span>{fa.reports.callsChartTitle}</span>
              </h3>
              <div className="h-64 w-full" dir="ltr">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={charts?.calls_by_direction || []}>
                    <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Bar dataKey="count" fill="#2563EB" radius={[8, 8, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card>

            <Card className="p-6">
              <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 mb-4 flex items-center gap-2">
                <Receipt className="h-4 w-4 text-emerald-600" />
                <span>{fa.reports.revenueChartTitle}</span>
              </h3>
              <div className="h-64 w-full" dir="ltr">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={charts?.revenue_breakdown || []}>
                    <CartesianGrid strokeDasharray="3 3" opacity={0.2} />
                    <XAxis dataKey="name" tick={{ fontSize: 11 }} />
                    <YAxis tick={{ fontSize: 11 }} />
                    <Tooltip />
                    <Bar dataKey="amount" fill="#10B981" radius={[8, 8, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </Card>
          </div>

          {/* Agent & Receptionist Performance Table with Devigner ContextMenu */}
          <Card className="p-0 overflow-hidden">
            <div className="p-4 border-b border-slate-100 dark:border-slate-800">
              <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
                {fa.reports.agentsTableTitle}
              </h3>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-right text-xs">
                <thead className="bg-slate-50/80 dark:bg-slate-800/40 border-b border-slate-200/80 dark:border-slate-800 text-slate-500 font-bold">
                  <tr>
                    <th className="px-4 py-3.5">نام اپراتور / کارشناس</th>
                    <th className="px-4 py-3.5">نقش</th>
                    <th className="px-4 py-3.5">شعبه</th>
                    <th className="px-4 py-3.5">تماس‌های پاسخ‌داده‌شده</th>
                    <th className="px-4 py-3.5">مجموع زمان مکالمه</th>
                    <th className="px-4 py-3.5">یادداشت‌های ثبت‌شده</th>
                    <th className="px-4 py-3.5">فاکتورهای صادرشده</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                  {agents.map((ag) => (
                    <ContextMenu
                      key={ag.id}
                      items={[
                        {
                          id: 'view-calls',
                          label: 'مشاهده تماس‌های این اپراتور',
                          onClick: () => navigate('/calls'),
                        },
                        {
                          id: 'view-users',
                          label: 'مدیریت دسترسی کاربر',
                          onClick: () => navigate('/users'),
                        },
                      ]}
                    >
                      <tr className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors cursor-pointer">
                        <td className="px-4 py-3.5 font-bold text-slate-900 dark:text-slate-100">
                          {ag.name}
                        </td>
                        <td className="px-4 py-3.5">
                          <Badge variant="primary" size="sm">
                            {ag.role}
                          </Badge>
                        </td>
                        <td className="px-4 py-3.5 text-slate-500">{ag.branch}</td>
                        <td className="px-4 py-3.5 font-bold">
                          {toPersianDigits(ag.calls_handled)} تماس
                        </td>
                        <td className="px-4 py-3.5 font-mono">
                          {toPersianDigits(ag.total_talk_seconds)} ثانیه
                        </td>
                        <td className="px-4 py-3.5">
                          {toPersianDigits(ag.notes_created)} مورد
                        </td>
                        <td className="px-4 py-3.5 text-emerald-600 font-bold">
                          {toPersianDigits(ag.invoices_created)} فاکتور
                        </td>
                      </tr>
                    </ContextMenu>
                  ))}
                </tbody>
              </table>
            </div>
          </Card>
        </>
      )}
    </div>
  )
}
