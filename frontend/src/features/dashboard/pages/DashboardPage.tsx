import React from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Users,
  Building,
  PhoneCall,
  Activity,
  Plus,
  ArrowUpRight,
  TrendingUp,
} from 'lucide-react'
import { useQuery } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { toPersianDigits } from '../../../lib/numerals'
import { formatJalali } from '../../../lib/jalali'
import { fa } from '../../../i18n/fa'

export const DashboardPage: React.FC = () => {
  const navigate = useNavigate()

  // Fetch quick metrics
  const { data: contactsData } = useQuery({
    queryKey: ['contacts-count'],
    queryFn: async () => {
      const res = await apiClient.get('/contacts?per_page=1')
      return res.data.total ?? 0
    },
  })

  const { data: branchesData } = useQuery({
    queryKey: ['branches-list'],
    queryFn: async () => {
      const res = await apiClient.get('/branches')
      return res.data.branches ?? []
    },
  })

  const { data: activeCallsData } = useQuery({
    queryKey: ['active-calls'],
    queryFn: async () => {
      const res = await apiClient.get('/calls/active')
      return res.data.active_calls ?? []
    },
  })

  return (
    <div className="space-y-6">
      {/* Top Welcome & Quick Actions */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.dashboard.title}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            سیستم ارتباط صوتی و پرونده یکپارچه مشتریان فعال و آماده به کار است.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button
            size="md"
            icon={<Plus className="h-4 w-4" />}
            onClick={() => navigate('/contacts')}
          >
            {fa.contacts.addNew}
          </Button>
        </div>
      </div>

      {/* Metrics Row */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
              {fa.dashboard.totalContacts}
            </span>
            <div className="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(contactsData ?? 0)}
            </div>
            <div className="flex items-center gap-1 text-[11px] text-emerald-600 font-medium mt-1">
              <TrendingUp className="h-3.5 w-3.5" />
              <span>مشتریان ثبت‌شده در CRM</span>
            </div>
          </div>
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/50 text-blue-600">
            <Users className="h-6 w-6" />
          </div>
        </Card>

        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
              {fa.branches.activeCount}
            </span>
            <div className="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(branchesData?.length ?? 1)}
            </div>
            <div className="text-[11px] text-slate-400 mt-1">
              مراکز تحت پوشش
            </div>
          </div>
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 dark:bg-amber-950/50 text-amber-600">
            <Building className="h-6 w-6" />
          </div>
        </Card>

        <Card
          hoverable
          onClick={() => navigate('/workspace')}
          className="flex items-center justify-between p-5"
        >
          <div>
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
              {fa.dashboard.activeCalls}
            </span>
            <div className="text-2xl font-black text-slate-900 dark:text-slate-100 mt-1">
              {toPersianDigits(activeCallsData?.length ?? 0)}
            </div>
            <div className="text-[11px] text-emerald-600 font-medium mt-1">
              ورود به میز کار منشی (Screen Pop)
            </div>
          </div>
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600">
            <PhoneCall className="h-6 w-6" />
          </div>
        </Card>

        <Card className="flex items-center justify-between p-5">
          <div>
            <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
              {fa.dashboard.systemHealth}
            </span>
            <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400 mt-2 flex items-center gap-1.5">
              <span className="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>پایدار و برخط (Online)</span>
            </div>
            <div className="text-[11px] text-slate-400 mt-1">
              زمان پاسخ: کمتر از ۳۰ میلی‌ثانیه
            </div>
          </div>
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-600">
            <Activity className="h-6 w-6" />
          </div>
        </Card>
      </div>

      {/* Quick Launch Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <Card
          hoverable
          onClick={() => navigate('/contacts')}
          className="flex items-center justify-between p-6 border-blue-100 dark:border-blue-900/40"
        >
          <div className="flex items-center gap-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-md shadow-blue-500/20">
              <Users className="h-6 w-6" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                {fa.contacts.title}
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                مشاهده لیست مخاطبین، یادداشت‌های پیگیری، جستجو و ویرایش
              </p>
            </div>
          </div>
          <ArrowUpRight className="h-5 w-5 text-slate-400" />
        </Card>

        <Card
          hoverable
          onClick={() => navigate('/branches')}
          className="flex items-center justify-between p-6 border-amber-100 dark:border-amber-900/40"
        >
          <div className="flex items-center gap-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-600 text-white shadow-md shadow-amber-500/20">
              <Building className="h-6 w-6" />
            </div>
            <div>
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                {fa.branches.title}
              </h3>
              <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                پیکربندی شعب، ساعت کاری، دفاتر و تفکیک دسترسی پرسنل
              </p>
            </div>
          </div>
          <ArrowUpRight className="h-5 w-5 text-slate-400" />
        </Card>
      </div>

      {/* System info bar */}
      <Card className="p-4 bg-slate-50/60 dark:bg-slate-900/40 border-slate-200/60 flex items-center justify-between text-xs text-slate-500">
        <div>
          امروز: <span className="font-bold text-slate-700 dark:text-slate-300">{formatJalali(new Date())}</span>
        </div>
        <div>
          منطقه زمانی: <span className="font-mono">Asia/Tehran</span>
        </div>
      </Card>
    </div>
  )
}
