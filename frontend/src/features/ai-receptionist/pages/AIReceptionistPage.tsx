import React, { useState } from 'react'
import {
  Bot,
  Sparkles,
  GitBranch,
  Play,
  Plus,
  CheckCircle2,
  Mic,
  Volume2,
  ArrowDown,
  PhoneForwarded,
  CalendarCheck,
  FileText,
  Cpu,
} from 'lucide-react'
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { apiClient } from '../../../lib/api/client'
import { Card } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Input } from '../../../components/ui/Input'
import { Badge } from '../../../components/ui/Badge'
import { Modal } from '../../../components/ui/Modal'
import { toPersianDigits } from '../../../lib/numerals'
import { fa } from '../../../i18n/fa'
import type {
  IvrFlow,
  FlowNode,
  CampaignWorkflowItem,
  AIInteractionSimulation,
} from '../../../types'

export const AIReceptionistPage: React.FC = () => {
  const queryClient = useQueryClient()

  const [activeTab, setActiveTab] = useState<'ivr_flow' | 'campaign_flow' | 'simulator'>('ivr_flow')
  const [selectedNode, setSelectedNode] = useState<FlowNode | null>(null)
  const [addNodeModalOpen, setAddNodeModalOpen] = useState(false)
  const [newNodeTitle, setNewNodeTitle] = useState('')
  const [newNodeType, setNewNodeType] = useState('book_appointment')
  const [newNodePrompt, setNewNodePrompt] = useState('')
  const [feedback, setFeedback] = useState<string | null>(null)

  // Simulator State
  const [simCaller, setSimCaller] = useState('09121112233')
  const [simUtterance, setSimUtterance] = useState(
    'سلام من سهراب سپهری هستم، می‌خواهم برای فردا ساعت ۱۰ یک نوبت ویزیت رزرو کنم.'
  )
  const [simDtmf, setSimDtmf] = useState('')
  const [simResult, setSimResult] = useState<AIInteractionSimulation | null>(null)

  const { data: ivrData, isLoading } = useQuery<{
    flows: IvrFlow[]
    reception_mode: 'human_only' | 'ai_only' | 'hybrid'
  }>({
    queryKey: ['ivr-flows'],
    queryFn: async () => {
      const res = await apiClient.get('/ivr/flows')
      return res.data
    },
  })

  const { data: cwData } = useQuery<{ workflows: CampaignWorkflowItem[] }>({
    queryKey: ['campaign-workflows'],
    queryFn: async () => {
      const res = await apiClient.get('/campaign-workflows')
      return res.data
    },
    enabled: activeTab === 'campaign_flow',
  })

  const activeFlow = ivrData?.flows?.[0]
  const receptionMode = ivrData?.reception_mode || 'hybrid'

  const modeMutation = useMutation({
    mutationFn: async (mode: 'human_only' | 'ai_only' | 'hybrid') => {
      const res = await apiClient.put('/ivr/reception-mode', { reception_mode: mode })
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['ivr-flows'] })
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 3500)
    },
  })

  const updateFlowMutation = useMutation({
    mutationFn: async (payload: { id: number; nodes: FlowNode[]; edges: any[] }) => {
      const res = await apiClient.put(`/ivr/flows/${payload.id}`, {
        nodes: payload.nodes,
        edges: payload.edges,
        is_active: true,
      })
      return res.data
    },
    onSuccess: (res) => {
      queryClient.invalidateQueries({ queryKey: ['ivr-flows'] })
      setFeedback(res.message)
      setTimeout(() => setFeedback(null), 3500)
    },
  })

  const simulateMutation = useMutation({
    mutationFn: async (payload: { caller_number: string; utterance?: string; dtmf?: string }) => {
      const res = await apiClient.post('/ivr/simulate', payload)
      return res.data
    },
    onSuccess: (res) => {
      setSimResult(res.simulation)
      queryClient.invalidateQueries({ queryKey: ['appointments-list'] })
      queryClient.invalidateQueries({ queryKey: ['message-logs'] })
    },
  })

  const handleAddNode = (e: React.FormEvent) => {
    e.preventDefault()
    if (!activeFlow || !newNodeTitle.trim()) return

    const newNodeId = `node_${newNodeType}_${Date.now()}`
    const updatedNodes: FlowNode[] = [
      ...(activeFlow.nodes || []),
      {
        id: newNodeId,
        type: newNodeType,
        position: { x: 380, y: 420 },
        data: {
          title: newNodeTitle,
          prompt: newNodePrompt || undefined,
          intent: newNodeType,
        },
      },
    ]

    const updatedEdges = [
      ...(activeFlow.edges || []),
      {
        id: `e_${Date.now()}`,
        source: 'node_intent_router',
        target: newNodeId,
        label: `نیت: ${newNodeTitle}`,
      },
    ]

    updateFlowMutation.mutate({
      id: activeFlow.id,
      nodes: updatedNodes,
      edges: updatedEdges,
    })

    setAddNodeModalOpen(false)
    setNewNodeTitle('')
    setNewNodePrompt('')
  }

  const presetPrompts = [
    {
      label: 'رزرو خودکار نوبت فردا',
      text: 'سلام من سهراب سپهری هستم، می‌خواهم برای فردا ساعت ۱۱ یک نوبت مشاوره رزرو کنم.',
    },
    {
      label: 'استعلام بدهی و فاکتور',
      text: 'سلام، مانده بدهی و وضعیت آخرین فاکتور حساب من چقدر است؟',
    },
    {
      label: 'ثبت تیکت پشتیبانی فنی',
      text: 'سلام، در سرویس خطا و مشکل فنی دارم، لطفاً تیکت پشتیبانی ثبت کنید.',
    },
    {
      label: 'انتقال به منشی انسانی',
      text: 'لطفاً من را به اپراتور یا منشی پذیرش وصل کنید.',
    },
  ]

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h1 className="text-xl font-black text-slate-900 dark:text-slate-100">
              {fa.aiIvr.title}
            </h1>
            <Badge variant="primary">Persian STT + TTS</Badge>
          </div>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {fa.aiIvr.subtitle}
          </p>
        </div>

        {/* Operating Mode Selector */}
        <div className="flex flex-wrap items-center gap-1.5 p-1.5 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
          {[
            { id: 'human_only' as const, label: fa.aiIvr.modeHumanOnly },
            { id: 'hybrid' as const, label: fa.aiIvr.modeHybrid },
            { id: 'ai_only' as const, label: fa.aiIvr.modeAiOnly },
          ].map((m) => (
            <button
              key={m.id}
              type="button"
              onClick={() => modeMutation.mutate(m.id)}
              className={`px-3 py-1.5 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                receptionMode === m.id
                  ? 'bg-blue-600 text-white shadow-xs'
                  : 'text-slate-600 dark:text-slate-300 hover:bg-white/60'
              }`}
            >
              {m.label}
            </button>
          ))}
        </div>
      </div>

      {feedback && (
        <div className="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-xs font-bold text-emerald-700 dark:text-emerald-300">
          {feedback}
        </div>
      )}

      {/* Navigation Tabs */}
      <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800">
        <button
          type="button"
          onClick={() => setActiveTab('ivr_flow')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'ivr_flow'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <GitBranch className="h-4 w-4" />
          <span>{fa.aiIvr.tabIvrFlow}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('simulator')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'simulator'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <Sparkles className="h-4 w-4" />
          <span>{fa.aiIvr.tabSimulator}</span>
        </button>

        <button
          type="button"
          onClick={() => setActiveTab('campaign_flow')}
          className={`flex items-center gap-2 pb-3 px-4 text-xs font-bold border-b-2 transition-all cursor-pointer ${
            activeTab === 'campaign_flow'
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-slate-400 hover:text-slate-600'
          }`}
        >
          <Cpu className="h-4 w-4" />
          <span>{fa.aiIvr.tabCampaignFlow}</span>
        </button>
      </div>

      {/* Tab 1: Visual IVR Flow Builder (RTL Interactive Graph) */}
      {activeTab === 'ivr_flow' && (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Visual Flow Canvas (2 cols) */}
          <Card className="lg:col-span-2 p-6 bg-slate-50/70 dark:bg-slate-900/60 border-dashed border-2 border-slate-200 dark:border-slate-800">
            <div className="flex flex-wrap items-center justify-between gap-3 pb-4 mb-6 border-b border-slate-200 dark:border-slate-800">
              <div>
                <h2 className="text-sm font-black text-slate-900 dark:text-slate-100">
                  {activeFlow?.name || 'جریان هوشمند پذیرش و نوبت‌دهی فارسی'}
                </h2>
                <p className="text-[11px] text-slate-500 mt-0.5">
                  روی هر گره کلیک کنید تا تنظیمات گفتار فارسی (TTS) و شروط مسیردهی آن را ویرایش نمایید.
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  icon={<Plus className="h-3.5 w-3.5" />}
                  onClick={() => setAddNodeModalOpen(true)}
                >
                  {fa.aiIvr.addNode}
                </Button>
                {activeFlow && (
                  <Button
                    size="sm"
                    icon={<CheckCircle2 className="h-3.5 w-3.5" />}
                    isLoading={updateFlowMutation.isPending}
                    onClick={() =>
                      updateFlowMutation.mutate({
                        id: activeFlow.id,
                        nodes: activeFlow.nodes,
                        edges: activeFlow.edges,
                      })
                    }
                  >
                    {fa.aiIvr.publishFlow}
                  </Button>
                )}
              </div>
            </div>

            {isLoading ? (
              <div className="py-12 text-center text-xs text-slate-400">
                {fa.common.loading}
              </div>
            ) : (
              <div className="space-y-4">
                {/* Entry & Router Nodes */}
                {activeFlow?.nodes?.slice(0, 2).map((node, index) => (
                  <React.Fragment key={node.id}>
                    <div
                      onClick={() => setSelectedNode(node)}
                      className={`mx-auto max-w-md p-4 rounded-2xl border-2 transition-all cursor-pointer shadow-sm ${
                        selectedNode?.id === node.id || simResult?.current_node_id === node.id
                          ? 'border-blue-600 bg-blue-50/50 dark:bg-blue-950/40'
                          : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-blue-400'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-1.5">
                        <span className="text-xs font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
                          <Bot className="h-4 w-4 text-blue-600" />
                          {node.data.title}
                        </span>
                        <Badge variant={index === 0 ? 'success' : 'primary'} size="sm">
                          {node.type}
                        </Badge>
                      </div>
                      {node.data.prompt && (
                        <p className="text-[11px] text-slate-500 leading-relaxed">
                          «{node.data.prompt}»
                        </p>
                      )}
                    </div>
                    <div className="flex justify-center">
                      <ArrowDown className="h-5 w-5 text-slate-400" />
                    </div>
                  </React.Fragment>
                ))}

                {/* Branching Action Nodes Grid */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 pt-2">
                  {activeFlow?.nodes?.slice(2).map((node) => (
                    <div
                      key={node.id}
                      onClick={() => setSelectedNode(node)}
                      className={`p-4 rounded-2xl border-2 transition-all cursor-pointer shadow-xs ${
                        selectedNode?.id === node.id || simResult?.current_node_id === node.id
                          ? 'border-emerald-500 bg-emerald-50/40 dark:bg-emerald-950/30'
                          : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:border-blue-400'
                      }`}
                    >
                      <div className="flex items-center justify-between mb-2">
                        <Badge variant="neutral" size="sm">
                          {node.type}
                        </Badge>
                        {node.type === 'book_appointment' ? (
                          <CalendarCheck className="h-4 w-4 text-emerald-600" />
                        ) : node.type === 'human_transfer' ? (
                          <PhoneForwarded className="h-4 w-4 text-amber-600" />
                        ) : (
                          <FileText className="h-4 w-4 text-blue-600" />
                        )}
                      </div>
                      <div className="text-xs font-bold text-slate-900 dark:text-slate-100">
                        {node.data.title}
                      </div>
                      <div className="text-[10px] text-slate-400 mt-1 font-mono">
                        شناسه: {node.id}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </Card>

          {/* Node Inspector & Quick Simulator Launch (1 col) */}
          <div className="space-y-4">
            <Card className="p-5">
              <h3 className="text-xs font-black text-slate-900 dark:text-slate-100 mb-3">
                بازرس و ویرایشگر گره (Node Inspector)
              </h3>
              {!selectedNode ? (
                <p className="text-xs text-slate-400 leading-relaxed">
                  برای مشاهده یا ویرایش جزئیات، یکی از گره‌های جریان را از بوم سمت راست انتخاب کنید.
                </p>
              ) : (
                <div className="space-y-3">
                  <div>
                    <span className="text-[11px] text-slate-400">عنوان گره:</span>
                    <div className="text-xs font-bold text-slate-900 dark:text-slate-100 mt-0.5">
                      {selectedNode.data.title}
                    </div>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-400">نوع عملیات:</span>
                    <div className="mt-1">
                      <Badge variant="primary">{selectedNode.type}</Badge>
                    </div>
                  </div>
                  {selectedNode.data.prompt && (
                    <div>
                      <span className="text-[11px] text-slate-400">متن گفتار فارسی (TTS):</span>
                      <p className="mt-1 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800 text-xs leading-relaxed">
                        {selectedNode.data.prompt}
                      </p>
                    </div>
                  )}
                </div>
              )}
            </Card>

            <Card className="p-5 bg-gradient-to-br from-blue-600 to-indigo-700 text-white">
              <div className="flex items-center gap-2 font-black text-sm mb-2">
                <Sparkles className="h-4 w-4" />
                <span>تست زنده هوش مصنوعی</span>
              </div>
              <p className="text-xs text-blue-100 leading-relaxed mb-4">
                می‌توانید همین حالا با گفتار فارسی، تشخیص نیت (Intent Detection)، رزرو خودکار نوبت و ارسال پیامک را آزمایش کنید.
              </p>
              <Button
                variant="secondary"
                size="sm"
                className="w-full"
                onClick={() => setActiveTab('simulator')}
              >
                ورود به شبیه‌ساز مکالمه فارسی
              </Button>
            </Card>
          </div>
        </div>
      )}

      {/* Tab 2: Live Persian AI Speech & DTMF Simulator */}
      {activeTab === 'simulator' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Caller Speech Input Card */}
          <Card className="p-6 space-y-4">
            <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Mic className="h-4 w-4 text-blue-600" />
              <span>ورودی گفتار تماس‌گیرنده (Persian STT & DTMF)</span>
            </h3>

            <Input
              label="شماره تلفن تماس‌گیرنده"
              dir="ltr"
              value={simCaller}
              onChange={(e) => setSimCaller(e.target.value)}
            />

            <div>
              <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                {fa.aiIvr.simInputLabel}
              </label>
              <textarea
                rows={3}
                value={simUtterance}
                onChange={(e) => {
                  setSimUtterance(e.target.value)
                  setSimDtmf('')
                }}
                className="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
              />
            </div>

            {/* Preset Persian Prompts */}
            <div>
              <span className="text-[11px] text-slate-400 block mb-2">
                جملات نمونه فارسی برای تست سریع:
              </span>
              <div className="flex flex-wrap gap-1.5">
                {presetPrompts.map((p, idx) => (
                  <button
                    key={idx}
                    type="button"
                    onClick={() => {
                      setSimUtterance(p.text)
                      setSimDtmf('')
                    }}
                    className="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950 text-[11px] font-bold text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                  >
                    {p.label}
                  </button>
                ))}
              </div>
            </div>

            {/* DTMF Keypad Shortcut */}
            <div>
              <span className="text-[11px] text-slate-400 block mb-2">
                یا فشردن کلید تلفن (DTMF):
              </span>
              <div className="flex items-center gap-2">
                {[
                  { digit: '1', label: '۱ (رزرو نوبت)' },
                  { digit: '2', label: '۲ (امور مالی)' },
                  { digit: '0', label: '۰ (اپراتور)' },
                  { digit: '9', label: '۹ (لغو DNC)' },
                ].map((k) => (
                  <button
                    key={k.digit}
                    type="button"
                    onClick={() =>
                      simulateMutation.mutate({
                        caller_number: simCaller,
                        dtmf: k.digit,
                      })
                    }
                    className="px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold hover:border-blue-500 cursor-pointer"
                  >
                    {k.label}
                  </button>
                ))}
              </div>
            </div>

            <Button
              size="md"
              className="w-full"
              icon={<Play className="h-4 w-4" />}
              isLoading={simulateMutation.isPending}
              onClick={() =>
                simulateMutation.mutate({
                  caller_number: simCaller,
                  utterance: simUtterance,
                  dtmf: simDtmf || undefined,
                })
              }
            >
              {fa.aiIvr.simRunButton}
            </Button>
          </Card>

          {/* AI Receptionist Execution Output Card */}
          <Card className="p-6 space-y-4">
            <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
              <Bot className="h-4 w-4 text-emerald-600" />
              <span>خروجی موتور منشی هوشمند (IVRFlowEngine)</span>
            </h3>

            {!simResult ? (
              <div className="py-12 text-center text-xs text-slate-400">
                یک جمله فارسی را انتخاب کرده و روی دکمه «پردازش هوشمند گفتار» کلیک کنید.
              </div>
            ) : (
              <div className="space-y-4">
                {/* Intent & Confidence */}
                <div className="p-3.5 rounded-2xl bg-blue-50/60 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800 flex items-center justify-between">
                  <div>
                    <span className="text-[11px] text-slate-500 block">
                      {fa.aiIvr.detectedIntent}:
                    </span>
                    <div className="text-sm font-black text-blue-700 dark:text-blue-300 mt-0.5">
                      {simResult.intent_label} ({simResult.intent})
                    </div>
                  </div>
                  <Badge variant="success">
                    دقت: %{toPersianDigits(Math.round(simResult.confidence * 100))}
                  </Badge>
                </div>

                {/* Synthesized Persian TTS Reply */}
                <div className="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-2">
                  <div className="flex items-center justify-between text-xs font-bold text-emerald-700 dark:text-emerald-300">
                    <span className="flex items-center gap-1.5">
                      <Volume2 className="h-4 w-4" />
                      {fa.aiIvr.aiVoiceReply}
                    </span>
                    <span className="font-mono text-[10px]">
                      {simResult.tts_audio.voice}
                    </span>
                  </div>
                  <p className="text-xs text-slate-800 dark:text-slate-100 leading-relaxed font-medium">
                    «{simResult.reply_text}»
                  </p>
                </div>

                {/* Extracted Entities */}
                {Object.keys(simResult.entities || {}).length > 0 && (
                  <div>
                    <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">
                      {fa.aiIvr.extractedEntities}:
                    </span>
                    <div className="flex flex-wrap gap-2">
                      {Object.entries(simResult.entities).map(([k, v]) => (
                        <span
                          key={k}
                          className="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-mono"
                        >
                          {k}: <strong>{String(v)}</strong>
                        </span>
                      ))}
                    </div>
                  </div>
                )}

                {/* Autonomous Actions Executed */}
                {simResult.actions_taken.length > 0 && (
                  <div>
                    <span className="text-xs font-bold text-slate-700 dark:text-slate-300 block mb-2">
                      {fa.aiIvr.autonomousActions}:
                    </span>
                    <div className="space-y-1.5">
                      {simResult.actions_taken.map((act, i) => (
                        <div
                          key={i}
                          className="flex items-center gap-2 text-xs text-emerald-700 dark:text-emerald-400 font-medium"
                        >
                          <CheckCircle2 className="h-4 w-4 shrink-0" />
                          <span>{act}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* AI Call Summary */}
                <div className="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800 text-xs">
                  <div className="font-bold text-slate-700 dark:text-slate-300 mb-1">
                    {fa.aiIvr.callSummary}:
                  </div>
                  <p className="text-slate-600 dark:text-slate-400">
                    {simResult.summary.summary}
                  </p>
                </div>
              </div>
            )}
          </Card>
        </div>
      )}

      {/* Tab 3: Campaign Workflow Visual Builder */}
      {activeTab === 'campaign_flow' && (
        <Card className="p-6 space-y-4">
          <h3 className="text-sm font-black text-slate-900 dark:text-slate-100">
            گردش کار بصری کمپین‌های صوتی خروجی
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {cwData?.workflows?.[0]?.nodes?.map((n) => (
              <div
                key={n.id}
                className="p-4 rounded-2xl border-2 border-blue-200 dark:border-blue-900 bg-blue-50/30 dark:bg-blue-950/20"
              >
                <Badge variant="primary" size="sm">
                  {n.type}
                </Badge>
                <div className="text-xs font-bold text-slate-900 dark:text-slate-100 mt-2">
                  {n.data.title}
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Add Node Modal */}
      <Modal
        isOpen={addNodeModalOpen}
        onClose={() => setAddNodeModalOpen(false)}
        title={fa.aiIvr.addNode}
      >
        <form onSubmit={handleAddNode} className="space-y-4">
          <Input
            label="عنوان گره در جریان"
            value={newNodeTitle}
            onChange={(e) => setNewNodeTitle(e.target.value)}
            placeholder="مثال: ثبت تیکت پشتیبانی فنی"
            required
          />

          <div>
            <label className="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
              نوع اقدام خودکار (Node Action Type)
            </label>
            <select
              value={newNodeType}
              onChange={(e) => setNewNodeType(e.target.value)}
              className="w-full min-h-[44px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
            >
              <option value="book_appointment">رزرو خودکار نوبت (book_appointment)</option>
              <option value="support_ticket">ثبت تیکت پشتیبانی (support_ticket)</option>
              <option value="lead_inquiry">ثبت سرنخ فروش در CRM (lead_inquiry)</option>
              <option value="billing_inquiry">استعلام صورت‌حساب (billing_inquiry)</option>
              <option value="human_transfer">انتقال به اپراتور انسانی (human_transfer)</option>
            </select>
          </div>

          <Input
            label="متن پاسخ صوتی فارسی (TTS Prompt)"
            value={newNodePrompt}
            onChange={(e) => setNewNodePrompt(e.target.value)}
            placeholder="جمله‌ای که منشی هوشمند به مشتری می‌گوید..."
          />

          <div className="flex justify-end gap-2 pt-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => setAddNodeModalOpen(false)}
            >
              {fa.common.cancel}
            </Button>
            <Button type="submit" isLoading={updateFlowMutation.isPending}>
              افزودن به جریان
            </Button>
          </div>
        </form>
      </Modal>
    </div>
  )
}
