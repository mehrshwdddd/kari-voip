import React, { useState, useEffect } from 'react'
import { Save, Check, PhoneCall, Server } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { VoipWizardModal } from '../components/VoipWizardModal'
import { fa } from '../../../i18n/fa'
import type { Company, VoipIntegration } from '../../../types'

export const SettingsPage: React.FC = () => {
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [website, setWebsite] = useState('')
  const [address, setAddress] = useState('')
  const [invoicePrefix, setInvoicePrefix] = useState('VR-')
  const [successMessage, setSuccessMessage] = useState<string | null>(null)
  const [voipWizardOpen, setVoipWizardOpen] = useState(false)

  const { data, isLoading } = useQuery<{ company: Company }>({
    queryKey: ['company-profile'],
    queryFn: async () => {
      const res = await apiClient.get('/company')
      return res.data
    },
  })

  const { data: voipData } = useQuery<{ integration: VoipIntegration }>({
    queryKey: ['voip-config'],
    queryFn: async () => {
      const res = await apiClient.get('/voip/config')
      return res.data
    },
  })

  useEffect(() => {
    if (data?.company) {
      setName(data.company.name || '')
      setPhone(data.company.phone || '')
      setEmail(data.company.email || '')
      setWebsite(data.company.website || '')
      setAddress(data.company.address || '')
      setInvoicePrefix(data.company.invoice_prefix || 'VR-')
    }
  }, [data])

  const updateMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.put('/company', payload)
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['company-profile'] })
      setSuccessMessage('اطلاعات با موفقیت ذخیره گردید.')
      setTimeout(() => setSuccessMessage(null), 3000)
    },
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    updateMutation.mutate({
      name,
      phone,
      email,
      website,
      address,
      invoice_prefix: invoicePrefix,
    })
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
            {fa.nav.settings}
          </h1>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            پیکربندی هویت سازمان، اتصال مرکز تلفن (VoIP) و پیشوند فاکتورها
          </p>
        </div>
        <Button
          size="md"
          icon={<PhoneCall className="h-4 w-4" />}
          onClick={() => setVoipWizardOpen(true)}
        >
          {fa.voipWizard.buttonLabel}
        </Button>
      </div>

      {/* VoIP Integration Status Card */}
      <Card className="p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-blue-100 dark:border-blue-900/40">
        <div className="flex items-center gap-4">
          <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 dark:bg-blue-950/60 text-blue-600">
            <Server className="h-6 w-6" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">
                {voipData?.integration?.name || 'Asterisk PBX مرکزی'}
              </h3>
              <Badge variant="success">متصل و فعال</Badge>
            </div>
            <p className="text-xs text-slate-500 mt-1 font-mono dir-ltr text-right">
              {voipData?.integration?.config?.host || '127.0.0.1'}:
              {voipData?.integration?.config?.port || 8088} (
              {voipData?.integration?.adapter_class || 'asterisk'})
            </p>
          </div>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => setVoipWizardOpen(true)}
        >
          پیکربندی خط تلفن
        </Button>
      </Card>

      <Card className="p-6">
        {isLoading ? (
          <div className="py-8 text-center text-xs text-slate-400">
            {fa.common.loading}
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">
            {successMessage && (
              <div className="p-3 rounded-xl bg-emerald-50 text-xs text-emerald-700 border border-emerald-200 flex items-center gap-2">
                <Check className="h-4 w-4" />
                <span>{successMessage}</span>
              </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Input
                label="نام سازمان / کسب‌وکار"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
              />
              <Input
                label="پیشوند شماره فاکتورها"
                dir="ltr"
                value={invoicePrefix}
                onChange={(e) => setInvoicePrefix(e.target.value)}
                required
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <Input
                label="تلفن تماس اصلی"
                dir="ltr"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
              />
              <Input
                label="ایمیل سازمانی"
                type="email"
                dir="ltr"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
              />
              <Input
                label="وب‌سایت"
                dir="ltr"
                value={website}
                onChange={(e) => setWebsite(e.target.value)}
              />
            </div>

            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                آدرس پستی
              </label>
              <textarea
                rows={2}
                value={address}
                onChange={(e) => setAddress(e.target.value)}
                className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 focus:border-blue-600 focus:outline-none"
              />
            </div>

            <div className="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
              <Button
                type="submit"
                size="md"
                isLoading={updateMutation.isPending}
                icon={<Save className="h-4 w-4" />}
              >
                {fa.common.save}
              </Button>
            </div>
          </form>
        )}
      </Card>

      <VoipWizardModal
        isOpen={voipWizardOpen}
        onClose={() => setVoipWizardOpen(false)}
      />
    </div>
  )
}
