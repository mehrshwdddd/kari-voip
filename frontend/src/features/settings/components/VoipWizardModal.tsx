import React, { useState, useEffect } from 'react'
import { Server, CheckCircle2, Wifi, ShieldCheck, ArrowLeft, ArrowRight } from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Modal } from '../../../components/ui/Modal'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { toPersianDigits } from '../../../lib/numerals'
import { fa } from '../../../i18n/fa'
import type { VoipIntegration } from '../../../types'

interface VoipWizardModalProps {
  isOpen: boolean
  onClose: () => void
}

export const VoipWizardModal: React.FC<VoipWizardModalProps> = ({ isOpen, onClose }) => {
  const queryClient = useQueryClient()
  const [step, setStep] = useState<1 | 2 | 3>(1)

  const [adapterClass, setAdapterClass] = useState<'asterisk' | 'issabel' | 'sip_webrtc'>('asterisk')
  const [name, setName] = useState('Asterisk PBX مرکزی')
  const [host, setHost] = useState('127.0.0.1')
  const [port, setPort] = useState('8088')
  const [username, setUsername] = useState('asterisk')
  const [secret, setSecret] = useState('')
  const [context, setContext] = useState('from-internal')
  const [receptionMode, setReceptionMode] = useState<'human_only' | 'ai_only' | 'hybrid'>('human_only')
  const [testResult, setTestResult] = useState<{
    connected: boolean
    latency_ms: number
    version: string
    message: string
  } | null>(null)

  const { data } = useQuery<{ integration: VoipIntegration }>({
    queryKey: ['voip-config'],
    queryFn: async () => {
      const res = await apiClient.get('/voip/config')
      return res.data
    },
    enabled: isOpen,
  })

  useEffect(() => {
    if (data?.integration) {
      const integ = data.integration
      setName(integ.name || 'Asterisk PBX مرکزی')
      setAdapterClass(integ.adapter_class || 'asterisk')
      setHost(integ.config?.host || '127.0.0.1')
      setPort(String(integ.config?.port || 8088))
      setContext(integ.config?.context || 'from-internal')
      setReceptionMode(integ.config?.reception_mode || 'human_only')
    }
  }, [data])

  const testMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.post('/voip/test-connection', {
        adapter_class: adapterClass,
        host,
        port: parseInt(port) || 8088,
      })
      return res.data
    },
    onSuccess: (res) => {
      setTestResult(res)
    },
  })

  const saveMutation = useMutation({
    mutationFn: async () => {
      const res = await apiClient.put('/voip/config', {
        name,
        adapter_class: adapterClass,
        host,
        port: parseInt(port) || 8088,
        username,
        secret: secret || undefined,
        context,
        reception_mode: receptionMode,
      })
      return res.data
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['voip-config'] })
      onClose()
    },
  })

  const selectProvider = (type: 'asterisk' | 'issabel' | 'sip_webrtc', defaultTitle: string, defaultPort: string) => {
    setAdapterClass(type)
    setName(defaultTitle)
    setPort(defaultPort)
    setTestResult(null)
  }

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={fa.voipWizard.title}
      description={fa.voipWizard.subtitle}
      maxWidth="xl"
    >
      {/* Stepper Header */}
      <div className="grid grid-cols-3 gap-2 mb-6 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 text-center text-xs font-bold">
        <button
          type="button"
          onClick={() => setStep(1)}
          className={`py-2 rounded-xl transition-all cursor-pointer ${
            step === 1
              ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
              : 'text-slate-500'
          }`}
        >
          {fa.voipWizard.step1}
        </button>
        <button
          type="button"
          onClick={() => setStep(2)}
          className={`py-2 rounded-xl transition-all cursor-pointer ${
            step === 2
              ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
              : 'text-slate-500'
          }`}
        >
          {fa.voipWizard.step2}
        </button>
        <button
          type="button"
          onClick={() => setStep(3)}
          className={`py-2 rounded-xl transition-all cursor-pointer ${
            step === 3
              ? 'bg-white dark:bg-slate-900 text-blue-600 shadow-xs'
              : 'text-slate-500'
          }`}
        >
          {fa.voipWizard.step3}
        </button>
      </div>

      {/* Step 1: Select Provider */}
      {step === 1 && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-3">
            {[
              {
                id: 'asterisk' as const,
                title: fa.voipWizard.providerAsterisk,
                desc: 'اتصال استاندارد از طریق رابط ARI/AMI به سرورهای استریسک و FreePBX',
                port: '8088',
              },
              {
                id: 'issabel' as const,
                title: fa.voipWizard.providerIssabel,
                desc: 'سازگار با مراکز تلفن ایزابل (Issabel 4 & 5) رایج در شرکت‌های ایرانی',
                port: '5038',
              },
              {
                id: 'sip_webrtc' as const,
                title: fa.voipWizard.providerSip,
                desc: 'اتصال مستقیم به خطوط ابری آسیاتک، شاتل‌تاک، رسپینا و مخابرات',
                port: '5060',
              },
            ].map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => selectProvider(item.id, item.title, item.port)}
                className={`w-full p-4 rounded-2xl border-2 text-right transition-all flex items-center justify-between cursor-pointer ${
                  adapterClass === item.id
                    ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/30'
                    : 'border-slate-200 dark:border-slate-800 hover:border-slate-300'
                }`}
              >
                <div className="flex items-center gap-3.5">
                  <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-950 text-blue-600">
                    <Server className="h-5 w-5" />
                  </div>
                  <div>
                    <div className="text-sm font-black text-slate-900 dark:text-slate-100">
                      {item.title}
                    </div>
                    <div className="text-xs text-slate-500 mt-0.5">{item.desc}</div>
                  </div>
                </div>
                {adapterClass === item.id && (
                  <CheckCircle2 className="h-5 w-5 text-blue-600 shrink-0" />
                )}
              </button>
            ))}
          </div>

          <div className="flex justify-end pt-4 border-t border-slate-100 dark:border-slate-800">
            <Button
              size="md"
              icon={<ArrowLeft className="h-4 w-4" />}
              onClick={() => setStep(2)}
            >
              مرحله بعد
            </Button>
          </div>
        </div>
      )}

      {/* Step 2: Network & Credentials */}
      {step === 2 && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="sm:col-span-2">
              <Input
                label={fa.voipWizard.hostLabel}
                dir="ltr"
                value={host}
                onChange={(e) => setHost(e.target.value)}
                placeholder="192.168.1.100 یا sip.company.ir"
              />
            </div>
            <Input
              label={fa.voipWizard.portLabel}
              dir="ltr"
              value={port}
              onChange={(e) => setPort(e.target.value)}
              placeholder="8088"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label={fa.voipWizard.usernameLabel}
              dir="ltr"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
            />
            <Input
              label={fa.voipWizard.secretLabel}
              type="password"
              dir="ltr"
              placeholder="••••••••"
              value={secret}
              onChange={(e) => setSecret(e.target.value)}
            />
          </div>

          <Input
            label={fa.voipWizard.contextLabel}
            dir="ltr"
            value={context}
            onChange={(e) => setContext(e.target.value)}
          />

          <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <Button
              variant="outline"
              size="md"
              icon={<ArrowRight className="h-4 w-4" />}
              onClick={() => setStep(1)}
            >
              مرحله قبل
            </Button>
            <Button
              size="md"
              icon={<ArrowLeft className="h-4 w-4" />}
              onClick={() => setStep(3)}
            >
              مرحله بعد
            </Button>
          </div>
        </div>
      )}

      {/* Step 3: Operating Mode & Connection Test */}
      {step === 3 && (
        <div className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-2">
              انتخاب حالت پاسخگویی سیستم:
            </label>
            <div className="space-y-2">
              {[
                { id: 'human_only' as const, label: fa.voipWizard.modeHuman },
                { id: 'hybrid' as const, label: fa.voipWizard.modeHybrid },
                { id: 'ai_only' as const, label: fa.voipWizard.modeAi },
              ].map((m) => (
                <label
                  key={m.id}
                  className={`flex items-center gap-3 p-3 rounded-xl border cursor-pointer text-xs font-medium ${
                    receptionMode === m.id
                      ? 'border-blue-600 bg-blue-50/40 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300'
                      : 'border-slate-200 dark:border-slate-800'
                  }`}
                >
                  <input
                    type="radio"
                    name="reception_mode"
                    checked={receptionMode === m.id}
                    onChange={() => setReceptionMode(m.id)}
                  />
                  <span>{m.label}</span>
                </label>
              ))}
            </div>
          </div>

          {/* Live Connection Test Box */}
          <div className="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 space-y-3">
            <div className="flex items-center justify-between">
              <div className="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                <Wifi className="h-4 w-4 text-blue-600" />
                <span>بررسی وضعیت ارتباط با {host}:{port}</span>
              </div>
              <Button
                size="sm"
                variant="secondary"
                isLoading={testMutation.isPending}
                onClick={() => testMutation.mutate()}
              >
                {fa.voipWizard.testButton}
              </Button>
            </div>

            {testResult && (
              <div className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs text-emerald-700 dark:text-emerald-300 space-y-1">
                <div className="font-bold flex items-center gap-1.5">
                  <ShieldCheck className="h-4 w-4" />
                  <span>{testResult.message}</span>
                </div>
                <div className="text-[11px] font-mono">
                  نسخه: {testResult.version} | پینگ: {toPersianDigits(testResult.latency_ms)} میلی‌ثانیه
                </div>
              </div>
            )}
          </div>

          <div className="flex items-center justify-between pt-4 border-t border-slate-100 dark:border-slate-800">
            <Button
              variant="outline"
              size="md"
              onClick={() => setStep(2)}
            >
              مرحله قبل
            </Button>
            <Button
              size="md"
              isLoading={saveMutation.isPending}
              onClick={() => saveMutation.mutate()}
            >
              {fa.voipWizard.saveAndFinish}
            </Button>
          </div>
        </div>
      )}
    </Modal>
  )
}
