/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/benchmark-page/index.js"
/*!*************************************!*\
  !*** ./src/benchmark-page/index.js ***!
  \*************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _style_css__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.css */ "./src/benchmark-page/style.css");
/* harmony import */ var _model_selector__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./model-selector */ "./src/benchmark-page/model-selector.js");
/* harmony import */ var _run_list__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./run-list */ "./src/benchmark-page/run-list.js");
/* harmony import */ var _run_details__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./run-details */ "./src/benchmark-page/run-details.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * WordPress dependencies
 */





/**
 * Internal dependencies
 */





/**
 * Root benchmark page application component.
 *
 * @return {JSX.Element} Benchmark page app element.
 */

function BenchmarkPageApp() {
  const [activeTab, setActiveTab] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('new-run');
  const [suites, setSuites] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [providers, setProviders] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [runs, setRuns] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [selectedRun, setSelectedRun] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);

  // New run form state
  const [runName, setRunName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [runDescription, setRunDescription] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [selectedSuite, setSelectedSuite] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('wp-core-v1');
  const [selectedModels, setSelectedModels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);

  // Running state
  const [runProgress, setRunProgress] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [isRunning, setIsRunning] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);

  // Load initial data
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadSuites();
    loadProviders();
    loadRuns();
  }, []);
  const loadSuites = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/gratis-ai-agent/v1/benchmark/suites'
      });
      setSuites(data);
    } catch (error) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load benchmark suites.', 'gratis-ai-agent')
      });
    }
  };
  const loadProviders = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/gratis-ai-agent/v1/providers'
      });
      setProviders(data.providers || []);
    } catch (error) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load providers.', 'gratis-ai-agent')
      });
    }
  };
  const loadRuns = async () => {
    try {
      const data = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/gratis-ai-agent/v1/benchmark/runs'
      });
      setRuns(data.runs || []);
    } catch (error) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load benchmark runs.', 'gratis-ai-agent')
      });
    }
  };
  const handleCreateRun = async () => {
    if (!runName.trim()) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please enter a run name.', 'gratis-ai-agent')
      });
      return;
    }
    if (selectedModels.length === 0) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Please select at least one model.', 'gratis-ai-agent')
      });
      return;
    }
    setIsLoading(true);
    setNotice(null);
    try {
      const run = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: '/gratis-ai-agent/v1/benchmark/runs',
        method: 'POST',
        data: {
          name: runName,
          description: runDescription,
          test_suite: selectedSuite,
          models: selectedModels
        }
      });
      setIsRunning(true);
      setRunProgress({
        completed: 0,
        total: run.questions_count
      });
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Benchmark run created. Starting tests…', 'gratis-ai-agent')
      });

      // Start running questions
      runBenchmarkQuestions(run.id);
    } catch (error) {
      setNotice({
        status: 'error',
        message: error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to create benchmark run.', 'gratis-ai-agent')
      });
      setIsLoading(false);
    }
  };
  const runBenchmarkQuestions = async runId => {
    const runNext = async () => {
      try {
        const result = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
          path: `/gratis-ai-agent/v1/benchmark/runs/${runId}/run-next`,
          method: 'POST'
        });
        if (result.status === 'completed') {
          setIsRunning(false);
          setIsLoading(false);
          setRunProgress(result.progress);
          setNotice({
            status: 'success',
            message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Benchmark completed!', 'gratis-ai-agent')
          });
          loadRuns();
          return;
        }
        setRunProgress(result.progress);

        // Continue to next question
        runNext();
      } catch (error) {
        setIsRunning(false);
        setIsLoading(false);
        setNotice({
          status: 'error',
          message: error.message || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Benchmark failed.', 'gratis-ai-agent')
        });
        loadRuns();
      }
    };
    runNext();
  };
  const handleViewRun = async runId => {
    setIsLoading(true);
    try {
      const run = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/gratis-ai-agent/v1/benchmark/runs/${runId}`
      });
      setSelectedRun(run);
      setActiveTab('view-run');
    } catch (error) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to load run details.', 'gratis-ai-agent')
      });
    }
    setIsLoading(false);
  };
  const handleDeleteRun = async runId => {
    if (
    // eslint-disable-next-line no-alert -- Intentional confirmation dialog for destructive delete action.
    !window.confirm((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Are you sure you want to delete this benchmark run?', 'gratis-ai-agent'))) {
      return;
    }
    setIsLoading(true);
    try {
      await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_2___default()({
        path: `/gratis-ai-agent/v1/benchmark/runs/${runId}`,
        method: 'DELETE'
      });
      setRuns(runs.filter(r => r.id !== runId));
      setNotice({
        status: 'success',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Benchmark run deleted.', 'gratis-ai-agent')
      });
    } catch (error) {
      setNotice({
        status: 'error',
        message: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Failed to delete run.', 'gratis-ai-agent')
      });
    }
    setIsLoading(false);
  };
  const tabs = [{
    name: 'new-run',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('New Benchmark', 'gratis-ai-agent')
  }, {
    name: 'history',
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('History', 'gratis-ai-agent')
  }];
  if (selectedRun) {
    tabs.push({
      name: 'view-run',
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Run Details', 'gratis-ai-agent')
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
    className: "gratis-ai-agent-benchmark-page",
    children: [notice && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
      status: notice.status,
      isDismissible: true,
      onRemove: () => setNotice(null),
      children: notice.message
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TabPanel, {
      className: "gratis-ai-agent-benchmark-tabs",
      activeClass: "is-active",
      tabs: tabs,
      initialTabName: activeTab,
      onSelect: setActiveTab,
      children: tab => {
        switch (tab.name) {
          case 'new-run':
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(NewRunTab, {
              runName: runName,
              setRunName: setRunName,
              runDescription: runDescription,
              setRunDescription: setRunDescription,
              suites: suites,
              selectedSuite: selectedSuite,
              setSelectedSuite: setSelectedSuite,
              providers: providers,
              selectedModels: selectedModels,
              setSelectedModels: setSelectedModels,
              onCreateRun: handleCreateRun,
              isLoading: isLoading,
              isRunning: isRunning,
              runProgress: runProgress
            });
          case 'history':
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_run_list__WEBPACK_IMPORTED_MODULE_6__["default"], {
              runs: runs,
              onViewRun: handleViewRun,
              onDeleteRun: handleDeleteRun,
              isLoading: isLoading
            });
          case 'view-run':
            return selectedRun ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_run_details__WEBPACK_IMPORTED_MODULE_7__["default"], {
              run: selectedRun,
              onBack: () => {
                setSelectedRun(null);
                setActiveTab('history');
              }
            }) : null;
          default:
            return null;
        }
      }
    })]
  });
}

/**
 * New Run Tab Component
 *
 * @param {Object}   props                   Component props.
 * @param {string}   props.runName           Run name.
 * @param {Function} props.setRunName        Set run name callback.
 * @param {string}   props.runDescription    Run description.
 * @param {Function} props.setRunDescription Set description callback.
 * @param {Array}    props.suites            Available suites.
 * @param {string}   props.selectedSuite     Selected suite.
 * @param {Function} props.setSelectedSuite  Set suite callback.
 * @param {Array}    props.providers         Available providers.
 * @param {Array}    props.selectedModels    Selected models.
 * @param {Function} props.setSelectedModels Set models callback.
 * @param {Function} props.onCreateRun       Create run callback.
 * @param {boolean}  props.isLoading         Loading state.
 * @param {boolean}  props.isRunning         Running state.
 * @param {Object}   props.runProgress       Progress object.
 * @return {JSX.Element} Component element.
 */
function NewRunTab({
  runName,
  setRunName,
  runDescription,
  setRunDescription,
  suites,
  selectedSuite,
  setSelectedSuite,
  providers,
  selectedModels,
  setSelectedModels,
  onCreateRun,
  isLoading,
  isRunning,
  runProgress
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
    className: "gratis-ai-agent-benchmark-new-run",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Card, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h2", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Configure Benchmark', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.CardBody, {
        children: [isRunning && runProgress && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "gratis-ai-agent-benchmark-progress",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
            status: "info",
            isDismissible: false,
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Benchmark is running…', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.ProgressBar, {
            value: runProgress.completed / runProgress.total * 100
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("p", {
            children: [runProgress.completed, " /", ' ', runProgress.total, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('questions completed', 'gratis-ai-agent')]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Run Name', 'gratis-ai-agent'),
          value: runName,
          onChange: setRunName,
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('e.g., Claude vs GPT-4 Comparison', 'gratis-ai-agent'),
          disabled: isRunning
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.TextareaControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Description', 'gratis-ai-agent'),
          value: runDescription,
          onChange: setRunDescription,
          placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Optional description of this benchmark run', 'gratis-ai-agent'),
          rows: 3,
          disabled: isRunning
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Test Suite', 'gratis-ai-agent'),
          value: selectedSuite,
          options: suites.map(suite => ({
            value: suite.slug,
            label: `${suite.name} (${suite.question_count} questions)`
          })),
          onChange: setSelectedSuite,
          disabled: isRunning
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "gratis-ai-agent-benchmark-models",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("h3", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select Models', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_model_selector__WEBPACK_IMPORTED_MODULE_5__["default"], {
            providers: providers,
            selectedModels: selectedModels,
            onChange: setSelectedModels,
            disabled: isRunning
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "primary",
          onClick: onCreateRun,
          disabled: isLoading || isRunning,
          isBusy: isLoading || isRunning,
          children: isRunning ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Running…', 'gratis-ai-agent') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Start Benchmark', 'gratis-ai-agent')
        })]
      })]
    })
  });
}
const container = document.getElementById('gratis-ai-agent-benchmark-root');
if (container) {
  const root = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(container);
  root.render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(BenchmarkPageApp, {}));
}

/***/ },

/***/ "./src/benchmark-page/model-selector.js"
/*!**********************************************!*\
  !*** ./src/benchmark-page/model-selector.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ModelSelector)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * WordPress dependencies
 */




/**
 * Model Selector Component
 *
 * @param {Object}   props                Component props.
 * @param {Array}    props.providers      Available providers.
 * @param {Array}    props.selectedModels Selected models.
 * @param {Function} props.onChange       Change callback.
 * @param {boolean}  props.disabled       Disabled state.
 * @return {JSX.Element} Component element.
 */

function ModelSelector({
  providers,
  selectedModels,
  onChange,
  disabled
}) {
  const [searchTerm, setSearchTerm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');

  // Define available models by provider
  const availableModels = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const models = [];

    // Built-in WordPress AI Client models
    models.push({
      provider_id: '',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('WordPress AI Client', 'gratis-ai-agent'),
      model_id: 'claude-sonnet-4',
      model_name: 'Claude Sonnet 4'
    });

    // Anthropic models
    models.push({
      provider_id: 'anthropic',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Anthropic', 'gratis-ai-agent'),
      model_id: 'claude-sonnet-4-20250514',
      model_name: 'Claude Sonnet 4 (2025-05-14)'
    }, {
      provider_id: 'anthropic',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Anthropic', 'gratis-ai-agent'),
      model_id: 'claude-opus-4-20250514',
      model_name: 'Claude Opus 4 (2025-05-14)'
    }, {
      provider_id: 'anthropic',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Anthropic', 'gratis-ai-agent'),
      model_id: 'claude-haiku-4-20250514',
      model_name: 'Claude Haiku 4 (2025-05-14)'
    });

    // OpenAI models
    models.push({
      provider_id: 'openai',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('OpenAI', 'gratis-ai-agent'),
      model_id: 'gpt-4o',
      model_name: 'GPT-4o'
    }, {
      provider_id: 'openai',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('OpenAI', 'gratis-ai-agent'),
      model_id: 'gpt-4o-mini',
      model_name: 'GPT-4o Mini'
    }, {
      provider_id: 'openai',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('OpenAI', 'gratis-ai-agent'),
      model_id: 'gpt-4-turbo',
      model_name: 'GPT-4 Turbo'
    }, {
      provider_id: 'openai',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('OpenAI', 'gratis-ai-agent'),
      model_id: 'gpt-3.5-turbo',
      model_name: 'GPT-3.5 Turbo'
    });

    // Google models
    models.push({
      provider_id: 'google',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Google', 'gratis-ai-agent'),
      model_id: 'gemini-2.5-pro',
      model_name: 'Gemini 2.5 Pro'
    }, {
      provider_id: 'google',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Google', 'gratis-ai-agent'),
      model_id: 'gemini-2.5-flash',
      model_name: 'Gemini 2.5 Flash'
    }, {
      provider_id: 'google',
      provider_name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Google', 'gratis-ai-agent'),
      model_id: 'gemini-1.5-pro',
      model_name: 'Gemini 1.5 Pro'
    });

    // Add any custom providers from the API
    if (providers && providers.length > 0) {
      providers.forEach(provider => {
        if (provider.models) {
          provider.models.forEach(model => {
            // Skip if already added
            const exists = models.some(m => m.provider_id === provider.id && m.model_id === model.id);
            if (!exists) {
              models.push({
                provider_id: provider.id,
                provider_name: provider.name,
                model_id: model.id,
                model_name: model.name || model.id
              });
            }
          });
        }
      });
    }
    return models;
  }, [providers]);

  // Filter models by search term
  const filteredModels = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    if (!searchTerm) {
      return availableModels;
    }
    const term = searchTerm.toLowerCase();
    return availableModels.filter(model => model.model_name.toLowerCase().includes(term) || model.provider_name.toLowerCase().includes(term));
  }, [availableModels, searchTerm]);

  // Group by provider
  const groupedModels = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const groups = {};
    filteredModels.forEach(model => {
      if (!groups[model.provider_name]) {
        groups[model.provider_name] = [];
      }
      groups[model.provider_name].push(model);
    });
    return groups;
  }, [filteredModels]);
  const isSelected = model => {
    return selectedModels.some(m => m.provider_id === model.provider_id && m.model_id === model.model_id);
  };
  const toggleModel = model => {
    if (isSelected(model)) {
      onChange(selectedModels.filter(m => !(m.provider_id === model.provider_id && m.model_id === model.model_id)));
    } else {
      onChange([...selectedModels, {
        provider_id: model.provider_id,
        model_id: model.model_id
      }]);
    }
  };
  const selectAll = () => {
    onChange(filteredModels.map(model => ({
      provider_id: model.provider_id,
      model_id: model.model_id
    })));
  };
  const deselectAll = () => {
    onChange([]);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
    className: "gratis-ai-agent-model-selector",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SearchControl, {
      value: searchTerm,
      onChange: setSearchTerm,
      placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Search models…', 'gratis-ai-agent')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "gratis-ai-agent-model-selector-actions",
      style: {
        margin: '12px 0'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        onClick: selectAll,
        disabled: disabled,
        size: "small",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Select All', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
        variant: "secondary",
        onClick: deselectAll,
        disabled: disabled,
        size: "small",
        style: {
          marginLeft: '8px'
        },
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Deselect All', 'gratis-ai-agent')
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("span", {
        style: {
          marginLeft: '12px',
          color: '#646970'
        },
        children: [selectedModels.length, ' ', (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('models selected', 'gratis-ai-agent')]
      })]
    }), Object.entries(groupedModels).map(([providerName, models]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "gratis-ai-agent-model-provider",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h4", {
        children: providerName
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        className: "gratis-ai-agent-model-list",
        children: models.map(model => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
          className: "gratis-ai-agent-model-item",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.CheckboxControl, {
            label: model.model_name,
            checked: isSelected(model),
            onChange: () => toggleModel(model),
            disabled: disabled
          })
        }, `${model.provider_id}-${model.model_id}`))
      })]
    }, providerName)), filteredModels.length === 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      style: {
        color: '#646970',
        textAlign: 'center',
        padding: '20px'
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('No models found matching your search.', 'gratis-ai-agent')
    })]
  });
}

/***/ },

/***/ "./src/benchmark-page/run-details.js"
/*!*******************************************!*\
  !*** ./src/benchmark-page/run-details.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ RunDetails)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/arrow-left.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/check.mjs");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/close-small.mjs");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * WordPress dependencies
 */




/**
 * Stat Card Component — wraps a single metric in a wp-admin Card.
 *
 * @param {Object} props       Component props.
 * @param {string} props.label Metric label.
 * @param {*}      props.value Metric value.
 * @return {JSX.Element} Stat card element.
 */

function StatCard({
  label,
  value
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
    className: "gratis-ai-agent-benchmark-stat-card",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h4", {
        children: label
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "stat-value",
        children: value
      })]
    })
  });
}

/**
 * Run Details Component
 *
 * @param {Object}   props        Component props.
 * @param {Object}   props.run    Run data.
 * @param {Function} props.onBack Back callback.
 * @return {JSX.Element} Component element.
 */
function RunDetails({
  run,
  onBack
}) {
  const results = run.results || [];
  const formatDate = dateString => {
    if (!dateString) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('N/A', 'gratis-ai-agent');
    }
    const date = new Date(dateString);
    return date.toLocaleString();
  };
  const correctCount = results.filter(r => r.is_correct).length;
  const accuracy = results.length > 0 ? Math.round(correctCount / results.length * 100) : 0;
  const avgLatency = results.length > 0 ? Math.round(results.reduce((sum, r) => sum + (r.latency_ms || 0), 0) / results.length) : 0;
  const totalTokens = results.reduce((sum, r) => sum + (r.prompt_tokens || 0) + (r.completion_tokens || 0), 0);

  // Group results by model
  const byModel = {};
  results.forEach(result => {
    const key = result.model_id;
    if (!byModel[key]) {
      byModel[key] = {
        model_id: result.model_id,
        provider_id: result.provider_id,
        total: 0,
        correct: 0
      };
    }
    byModel[key].total++;
    if (result.is_correct) {
      byModel[key].correct++;
    }
  });

  // Group results by category
  const byCategory = {};
  results.forEach(result => {
    const key = result.question_category;
    if (!byCategory[key]) {
      byCategory[key] = {
        category: key,
        total: 0,
        correct: 0
      };
    }
    byCategory[key].total++;
    if (result.is_correct) {
      byCategory[key].correct++;
    }
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "gratis-ai-agent-benchmark-run-details",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "tertiary",
      onClick: onBack,
      icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"],
      style: {
        marginBottom: '16px'
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Back to History', 'gratis-ai-agent')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h2", {
          children: run.name
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: [run.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          children: run.description
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Test Suite:', 'gratis-ai-agent')
          }), ' ', run.test_suite]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Started:', 'gratis-ai-agent')
          }), ' ', formatDate(run.started_at)]
        }), run.completed_at && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("strong", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Completed:', 'gratis-ai-agent')
          }), ' ', formatDate(run.completed_at)]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      className: "gratis-ai-agent-benchmark-summary",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(StatCard, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Total Questions', 'gratis-ai-agent'),
        value: results.length
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(StatCard, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Accuracy', 'gratis-ai-agent'),
        value: `${accuracy}%`
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(StatCard, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Correct', 'gratis-ai-agent'),
        value: correctCount
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(StatCard, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Avg Latency', 'gratis-ai-agent'),
        value: `${avgLatency}ms`
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(StatCard, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Total Tokens', 'gratis-ai-agent'),
        value: totalTokens.toLocaleString()
      })]
    }), Object.keys(byModel).length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      style: {
        marginTop: '20px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Results by Model', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
          className: "wp-list-table widefat fixed striped",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Model', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Provider', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Total', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Correct', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Accuracy', 'gratis-ai-agent')
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
            children: Object.values(byModel).map(model => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: model.model_id
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: model.provider_id || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Default', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: model.total
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: model.correct
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("td", {
                children: [Math.round(model.correct / model.total * 100), "%"]
              })]
            }, model.model_id))
          })]
        })
      })]
    }), Object.keys(byCategory).length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      style: {
        marginTop: '20px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Results by Category', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
          className: "wp-list-table widefat fixed striped",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Category', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Total', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Correct', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Accuracy', 'gratis-ai-agent')
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
            children: Object.values(byCategory).map(cat => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: cat.category
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: cat.total
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                children: cat.correct
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("td", {
                children: [Math.round(cat.correct / cat.total * 100), "%"]
              })]
            }, cat.category))
          })]
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      style: {
        marginTop: '20px'
      },
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h3", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Detailed Results', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          className: "gratis-ai-agent-benchmark-results-table",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("table", {
            className: "wp-list-table widefat fixed striped",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("thead", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  style: {
                    width: '30px'
                  }
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Question ID', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Category', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Model', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Correct', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Answer', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("th", {
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Latency', 'gratis-ai-agent')
                })]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("tbody", {
              children: results.map((result, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: result.is_correct ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
                    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
                    style: {
                      color: '#1a7f37'
                    }
                  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
                    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
                    style: {
                      color: '#cf222e'
                    }
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: result.question_id
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: result.question_category
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: result.model_id
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("td", {
                  children: result.correct_answer
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("td", {
                  className: result.is_correct ? 'is-correct' : 'is-incorrect',
                  children: [result.model_answer.substring(0, 50), result.model_answer.length > 50 ? '...' : '']
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("td", {
                  children: [result.latency_ms, "ms"]
                })]
              }, index))
            })]
          })
        })
      })]
    })]
  });
}

/***/ },

/***/ "./src/benchmark-page/run-list.js"
/*!****************************************!*\
  !*** ./src/benchmark-page/run-list.js ***!
  \****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ RunList)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * WordPress dependencies
 */



/**
 * Run List Component
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.runs        Benchmark runs.
 * @param {Function} props.onViewRun   View run callback.
 * @param {Function} props.onDeleteRun Delete run callback.
 * @param {boolean}  props.isLoading   Loading state.
 * @return {JSX.Element} Component element.
 */

function RunList({
  runs,
  onViewRun,
  onDeleteRun,
  isLoading
}) {
  const formatDate = dateString => {
    if (!dateString) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('N/A', 'gratis-ai-agent');
    }
    const date = new Date(dateString);
    return date.toLocaleString();
  };
  const formatDuration = (startedAt, completedAt) => {
    if (!startedAt || !completedAt) {
      return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('N/A', 'gratis-ai-agent');
    }
    const start = new Date(startedAt);
    const end = new Date(completedAt);
    const diff = Math.round((end - start) / 1000);
    if (diff < 60) {
      return `${diff}s`;
    } else if (diff < 3600) {
      return `${Math.round(diff / 60)}m`;
    }
    return `${Math.round(diff / 3600)}h ${Math.round(diff % 3600 / 60)}m`;
  };
  const getStatusClass = status => {
    switch (status) {
      case 'pending':
        return 'pending';
      case 'running':
        return 'running';
      case 'completed':
        return 'completed';
      case 'failed':
        return 'failed';
      default:
        return '';
    }
  };
  if (isLoading && runs.length === 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "gratis-ai-agent-benchmark-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {})
    });
  }
  if (runs.length === 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
          className: "gratis-ai-agent-benchmark-empty",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No benchmark runs yet.', 'gratis-ai-agent')
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Create a new benchmark to get started.', 'gratis-ai-agent')
          })]
        })
      })
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: "gratis-ai-agent-benchmark-run-list",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h2", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Benchmark History', 'gratis-ai-agent')
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.CardBody, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("table", {
          className: "wp-list-table widefat fixed striped",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("thead", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Name', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Suite', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Status', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Progress', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Started', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Duration', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("th", {
                children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Actions', 'gratis-ai-agent')
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("tbody", {
            children: runs.map(run => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("td", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("strong", {
                  children: run.name
                }), run.description && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("p", {
                  className: "description",
                  style: {
                    margin: '4px 0 0'
                  },
                  children: run.description
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("td", {
                children: run.test_suite
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("td", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
                  className: `gratis-ai-agent-benchmark-status ${getStatusClass(run.status)}`,
                  children: run.status
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("td", {
                children: run.questions_count > 0 ? `${run.completed_count} / ${run.questions_count}` : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('N/A', 'gratis-ai-agent')
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("td", {
                children: formatDate(run.started_at)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("td", {
                children: formatDuration(run.started_at, run.completed_at)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("td", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                  variant: "secondary",
                  size: "small",
                  onClick: () => onViewRun(run.id),
                  style: {
                    marginRight: '8px'
                  },
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('View', 'gratis-ai-agent')
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                  variant: "tertiary",
                  isDestructive: true,
                  size: "small",
                  onClick: () => onDeleteRun(run.id),
                  children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Delete', 'gratis-ai-agent')
                })]
              })]
            }, run.id))
          })]
        })
      })]
    })
  });
}

/***/ },

/***/ "./src/benchmark-page/style.css"
/*!**************************************!*\
  !*** ./src/benchmark-page/style.css ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/components"
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["components"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ },

/***/ "@wordpress/i18n"
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
(module) {

module.exports = window["wp"]["i18n"];

/***/ },

/***/ "@wordpress/primitives"
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
(module) {

module.exports = window["wp"]["primitives"];

/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/arrow-left.mjs"
/*!***************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/arrow-left.mjs ***!
  \***************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ arrow_left_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/arrow-left.tsx


var arrow_left_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M20 11.2H6.8l3.7-3.7-1-1L3.9 12l5.6 5.5 1-1-3.7-3.7H20z" }) });

//# sourceMappingURL=arrow-left.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/check.mjs"
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/check.mjs ***!
  \**********************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ check_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/check.tsx


var check_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M16.5 7.5 10 13.9l-2.5-2.4-1 1 3.5 3.6 7.5-7.6z" }) });

//# sourceMappingURL=check.mjs.map


/***/ },

/***/ "./node_modules/@wordpress/icons/build-module/library/close-small.mjs"
/*!****************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/close-small.mjs ***!
  \****************************************************************************/
(__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ close_small_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
// packages/icons/src/library/close-small.tsx


var close_small_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M12 13.06l3.712 3.713 1.061-1.06L13.061 12l3.712-3.712-1.06-1.06L12 10.938 8.288 7.227l-1.061 1.06L10.939 12l-3.712 3.712 1.06 1.061L12 13.061z" }) });

//# sourceMappingURL=close-small.mjs.map


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var [chunkIds, fn, priority] = deferred[i];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"benchmark-page": 0,
/******/ 			"./style-benchmark-page": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var [chunkIds, moreModules, runtime] = data;
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkgratis_ai_agent"] = globalThis["webpackChunkgratis_ai_agent"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["./style-benchmark-page"], () => (__webpack_require__("./src/benchmark-page/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=benchmark-page.js.map