import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import {
  Building2,
  GitBranch,
  ShieldAlert,
  PhoneCall,
  CheckCircle2,
  ArrowRight,
  ArrowLeft,
  Sparkles,
  Server,
  Lock,
  Mail,
  Phone,
  User as UserIcon,
} from 'lucide-react'
import { apiClient } from '../../../lib/api/client'
import { useAuthStore } from '../../../stores/authStore'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { toPersianDigits } from '../../../lib/numerals'
import { fa } from '../../../i18n/fa'

export const SetupWizardPage: React.FC = () => {
  const navigate = useNavigate()
  const setAuth = useAuthStore((s) => s.setAuth)

  const [currentStep, setCurrentStep] = useState(1)
  const [isChecking, setIsChecking] = useState(true)
  const [isAlreadyInstalled, setIsAlreadyInstalled] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  // Form State
  const [formData, setFormData] = useState({
    company_name: '',
    company_phone: '',
    company_address: '',
    branch_name: 'شعبه مرکزی',
    branch_code: 'MAIN',
    admin_name: '',
    admin_email: '',
    admin_phone: '',
    admin_password: '',
    voip_adapter: 'asterisk',
    voip_host: '127.0.0.1',
    voip_port: 5038,
  })

  // Check setup status
  useEffect(() => {
    async function checkStatus() {
      try {
        const res = await apiClient.get('/setup/status')
        if (res.data?.data?.is_installed) {
          setIsAlreadyInstalled(true)
        }
      } catch (err) {
        console.error('Failed to check setup status', err)
      } finally {
        setIsChecking(false)
      }
    }
    checkStatus()
  }, [])

  const handleNext = () => {
    setErrorMessage(null)
    if (currentStep === 1) {
      if (!formData.company_name || !formData.company_phone) {
        setErrorMessage('لطفاً نام سازمان و تلفن تماس را وارد نمایید.')
        return
      }
    } else if (currentStep === 2) {
      if (!formData.branch_name || !formData.branch_code) {
        setErrorMessage('نام و کد شعبه مرکزی الزامی است.')
        return
      }
    } else if (currentStep === 3) {
      if (!formData.admin_name || !formData.admin_email || !formData.admin_phone || !formData.admin_password) {
        setErrorMessage('تمام فیلدهای حساب مدیر ارشد الزامی است.')
        return
      }
      if (formData.admin_password.length < 8) {
        setErrorMessage('رمز عبور مدیر باید حداقل ۸ کاراکتر باشد.')
        return
      }
    }
    setCurrentStep((prev) => prev + 1)
  }

  const handlePrev = () => {
    setErrorMessage(null)
    setCurrentStep((prev) => Math.max(1, prev - 1))
  }

  const handleInstall = async () => {
    setIsSubmitting(true)
    setErrorMessage(null)

    try {
      const res = await apiClient.post('/setup/install', formData)
      const data = res.data?.data
      if (data?.token && data?.user) {
        setAuth(data.user, data.token)
        navigate('/workspace', { replace: true })
      } else {
        navigate('/login', { replace: true })
      }
    } catch (err: any) {
      const msg = err.response?.data?.message || 'خطا در فرآیند نصب سامانه. لطفاً فیلدها را بازبینی کنید.'
      setErrorMessage(msg)
      setIsSubmitting(false)
    }
  }

  if (isChecking) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900" dir="rtl">
        <div className="text-sm text-gray-500 flex items-center gap-2">
          <Sparkles className="w-5 h-5 animate-spin text-emerald-600" />
          در حال بررسی وضعیت استقرار سامانه...
        </div>
      </div>
    )
  }

  if (isAlreadyInstalled) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-900 p-4" dir="rtl">
        <Card className="max-w-md w-full p-8 text-center space-y-4 shadow-xl">
          <div className="w-14 h-14 bg-emerald-100 dark:bg-emerald-950 text-emerald-600 dark:text-emerald-400 rounded-2xl flex items-center justify-center mx-auto">
            <CheckCircle2 className="w-8 h-8" />
          </div>
          <h2 className="text-xl font-bold text-gray-900 dark:text-white">سامانه قبلاً راه‌اندازی شده است</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400">
            سازمان و حساب کاربری مدیر ارشد بر روی این سرور پیکربندی شده است.
          </p>
          <Button onClick={() => navigate('/login')} className="w-full">
            ورود به حساب کاربری
          </Button>
        </Card>
      </div>
    )
  }

  const steps = [
    { num: 1, label: fa.setup.stepOrg, icon: Building2 },
    { num: 2, label: fa.setup.stepBranch, icon: GitBranch },
    { num: 3, label: fa.setup.stepAdmin, icon: ShieldAlert },
    { num: 4, label: fa.setup.stepVoip, icon: PhoneCall },
    { num: 5, label: fa.setup.stepFinish, icon: Sparkles },
  ]

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col justify-center py-12 sm:px-6 lg:px-8" dir="rtl">
      <div className="sm:mx-auto sm:w-full sm:max-w-2xl">
        {/* Header */}
        <div className="text-center mb-8">
          <div className="inline-flex items-center justify-center w-12 h-12 bg-emerald-600 text-white rounded-2xl shadow-lg shadow-emerald-600/30 mb-3">
            <Server className="w-6 h-6" />
          </div>
          <h1 className="text-2xl font-black text-gray-900 dark:text-white">
            {fa.setup.title}
          </h1>
          <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {fa.setup.subtitle}
          </p>
        </div>

        {/* Stepper Progress Indicator */}
        <div className="mb-8 px-4">
          <div className="flex items-center justify-between relative">
            <div className="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-0.5 bg-gray-200 dark:bg-gray-800 -z-0" />
            {steps.map((st) => {
              const Icon = st.icon
              const isPassed = currentStep > st.num
              const isCurrent = currentStep === st.num

              return (
                <div key={st.num} className="relative z-10 flex flex-col items-center">
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold transition-all ${
                      isPassed
                        ? 'bg-emerald-600 text-white ring-4 ring-emerald-50 dark:ring-emerald-950'
                        : isCurrent
                        ? 'bg-emerald-500 text-white ring-4 ring-emerald-100 dark:ring-emerald-900 shadow-md'
                        : 'bg-white dark:bg-gray-800 text-gray-400 border border-gray-200 dark:border-gray-700'
                    }`}
                  >
                    {isPassed ? <CheckCircle2 className="w-5 h-5" /> : <Icon className="w-4 h-4" />}
                  </div>
                  <span
                    className={`mt-2 text-[11px] font-medium hidden sm:block ${
                      isCurrent
                        ? 'text-emerald-600 dark:text-emerald-400 font-bold'
                        : 'text-gray-400'
                    }`}
                  >
                    {st.label}
                  </span>
                </div>
              )
            })}
          </div>
        </div>

        {/* Wizard Form Card */}
        <Card className="p-6 sm:p-8 shadow-xl border-gray-100 dark:border-gray-800">
          {errorMessage && (
            <div className="mb-6 p-3 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-xs text-rose-800 dark:text-rose-300">
              {errorMessage}
            </div>
          )}

          {/* STEP 1: ORGANIZATION */}
          {currentStep === 1 && (
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Building2 className="w-5 h-5 text-emerald-600" />
                  {fa.setup.stepOrg}
                </h3>
                <p className="text-xs text-gray-500">مشخصات اصلی و شماره تماس کسب‌وکار یا مرکز درمانی</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.companyName} *
                </label>
                <input
                  type="text"
                  placeholder="مثلاً: کلینیک تخصصی مهر یا شرکت بازرگانی پارس"
                  value={formData.company_name}
                  onChange={(e) => setFormData({ ...formData, company_name: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.companyPhone} *
                </label>
                <input
                  type="text"
                  placeholder="02188880000"
                  value={formData.company_phone}
                  onChange={(e) => setFormData({ ...formData, company_phone: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.companyAddress}
                </label>
                <textarea
                  rows={2}
                  placeholder="تهران، خیابان ولیعصر..."
                  value={formData.company_address}
                  onChange={(e) => setFormData({ ...formData, company_address: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>
            </div>
          )}

          {/* STEP 2: CENTRAL BRANCH */}
          {currentStep === 2 && (
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <GitBranch className="w-5 h-5 text-emerald-600" />
                  {fa.setup.stepBranch}
                </h3>
                <p className="text-xs text-gray-500">
                  سامانه به صورت تک‌کسب‌وکاری با قابلیت چندشعبه‌ای طراحی شده است. اولین شعبه شما به عنوان شعبه مرکزی ثبت می‌گردد.
                </p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.branchName} *
                </label>
                <input
                  type="text"
                  value={formData.branch_name}
                  onChange={(e) => setFormData({ ...formData, branch_name: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                />
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.branchCode} *
                </label>
                <input
                  type="text"
                  value={formData.branch_code}
                  onChange={(e) => setFormData({ ...formData, branch_code: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono uppercase"
                />
              </div>
            </div>
          )}

          {/* STEP 3: SUPER ADMIN ACCOUNT */}
          {currentStep === 3 && (
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <ShieldAlert className="w-5 h-5 text-emerald-600" />
                  {fa.setup.stepAdmin}
                </h3>
                <p className="text-xs text-gray-500">مشخصات اولین حساب مدیریت کل (Owner) با بالاترین سطح دسترسی</p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.adminName} *
                </label>
                <div className="relative">
                  <input
                    type="text"
                    placeholder="مثلاً: علی رضایی"
                    value={formData.admin_name}
                    onChange={(e) => setFormData({ ...formData, admin_name: e.target.value })}
                    className="w-full pl-3 pr-9 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                  />
                  <UserIcon className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {fa.setup.adminEmail} *
                  </label>
                  <div className="relative">
                    <input
                      type="email"
                      placeholder="admin@example.com"
                      value={formData.admin_email}
                      onChange={(e) => setFormData({ ...formData, admin_email: e.target.value })}
                      className="w-full pl-3 pr-9 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                    />
                    <Mail className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                  </div>
                </div>

                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {fa.setup.adminPhone} *
                  </label>
                  <div className="relative">
                    <input
                      type="text"
                      placeholder="09121234567"
                      value={formData.admin_phone}
                      onChange={(e) => setFormData({ ...formData, admin_phone: e.target.value })}
                      className="w-full pl-3 pr-9 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                    />
                    <Phone className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.adminPassword} *
                </label>
                <div className="relative">
                  <input
                    type="password"
                    placeholder="حداقل ۸ کاراکتر امن"
                    value={formData.admin_password}
                    onChange={(e) => setFormData({ ...formData, admin_password: e.target.value })}
                    className="w-full pl-3 pr-9 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono"
                  />
                  <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2" />
                </div>
              </div>
            </div>
          )}

          {/* STEP 4: VOIP INTEGRATION */}
          {currentStep === 4 && (
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <PhoneCall className="w-5 h-5 text-emerald-600" />
                  {fa.setup.stepVoip}
                </h3>
                <p className="text-xs text-gray-500">
                  اتصال سرور سانترال تلفنی جهت پردازش بلادرنگ تماس‌ها (می‌توانید بعداً نیز در بخش تنظیمات تغییر دهید)
                </p>
              </div>

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  {fa.setup.voipAdapter}
                </label>
                <select
                  value={formData.voip_adapter}
                  onChange={(e) => setFormData({ ...formData, voip_adapter: e.target.value })}
                  className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white"
                >
                  <option value="asterisk">استریسک خالص (Asterisk AMI / ARI)</option>
                  <option value="issabel">ایزابل فارسی (Issabel PBX)</option>
                  <option value="generic_sip">پروتکل عمومی SIP PBX</option>
                </select>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {fa.setup.voipHost}
                  </label>
                  <input
                    type="text"
                    value={formData.voip_host}
                    onChange={(e) => setFormData({ ...formData, voip_host: e.target.value })}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {fa.setup.voipPort}
                  </label>
                  <input
                    type="number"
                    value={formData.voip_port}
                    onChange={(e) => setFormData({ ...formData, voip_port: parseInt(e.target.value, 10) || 5038 })}
                    className="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-white font-mono dir-ltr text-right"
                  />
                </div>
              </div>
            </div>
          )}

          {/* STEP 5: REVIEW & FINISH */}
          {currentStep === 5 && (
            <div className="space-y-4">
              <div>
                <h3 className="text-base font-bold text-gray-900 dark:text-white flex items-center gap-2">
                  <Sparkles className="w-5 h-5 text-emerald-600" />
                  {fa.setup.stepFinish}
                </h3>
                <p className="text-xs text-gray-500">
                  خلاصه تنظیمات جهت تأیید و راه‌اندازی نهایی پایگاه داده و زیرساخت
                </p>
              </div>

              <div className="p-4 bg-gray-50 dark:bg-gray-800/60 rounded-xl space-y-2 text-xs">
                <div className="flex justify-between">
                  <span className="text-gray-500">نام سازمان:</span>
                  <span className="font-bold text-gray-900 dark:text-white">{formData.company_name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">تلفن سازمانی:</span>
                  <span className="font-mono text-gray-900 dark:text-white">{formData.company_phone}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">شعبه مرکزی:</span>
                  <span className="font-bold text-gray-900 dark:text-white">{formData.branch_name} ({formData.branch_code})</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">مدیر سیستم:</span>
                  <span className="font-bold text-gray-900 dark:text-white">{formData.admin_name} ({formData.admin_email})</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-gray-500">سرور سانترال:</span>
                  <span className="font-mono text-gray-900 dark:text-white">{formData.voip_adapter} @ {formData.voip_host}:{formData.voip_port}</span>
                </div>
              </div>

              <div className="p-3 bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs text-emerald-800 dark:text-emerald-300">
                با کلیک بر روی راه‌اندازی، مایگریشن‌ها نهایی شده، دسترسی‌های امنیتی اعمال و بلافاصله به میزکار متصل می‌شوید.
              </div>
            </div>
          )}

          {/* Stepper Buttons */}
          <div className="mt-8 pt-4 border-t dark:border-gray-800 flex items-center justify-between">
            {currentStep > 1 ? (
              <Button
                variant="outline"
                onClick={handlePrev}
                disabled={isSubmitting}
                className="flex items-center gap-1.5"
              >
                <ArrowRight className="w-4 h-4" />
                مرحله قبل
              </Button>
            ) : (
              <div />
            )}

            {currentStep < 5 ? (
              <Button onClick={handleNext} className="flex items-center gap-1.5">
                مرحله بعد
                <ArrowLeft className="w-4 h-4" />
              </Button>
            ) : (
              <Button
                onClick={handleInstall}
                disabled={isSubmitting}
                className="flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700"
              >
                {isSubmitting ? (
                  <>
                    <Sparkles className="w-4 h-4 animate-spin" />
                    {fa.setup.installing}
                  </>
                ) : (
                  <>
                    <CheckCircle2 className="w-4 h-4" />
                    {fa.setup.launchApp}
                  </>
                )}
              </Button>
            )}
          </div>
        </Card>
      </div>
    </div>
  )
}
export default SetupWizardPage
