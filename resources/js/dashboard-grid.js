import { GridStack } from 'gridstack'
import { finestRow, snapHeight, storedLayout } from './layout-units.js'

const COLUMNS = 12

const MIN_STRETCH = 48

const MIN_RELOAD_MS = 400

const SPAN_VARIABLES = ['--col-span-csm', '--col-span-ncmd', '--col-span-cxl', '--col-span-nclg']

const ROW_ATTRIBUTES = ['gs-y', 'gs-h', 'gs-min-h', 'gs-max-h']

export default function dashboardGrid({ editable, printing, options }) {
    return {
        grid: null,

        resizing: false,

        observer: null,

        init() {
            this.rowsPerCell = options.cellHeight / finestRow(options.cellHeight)

            this.$refs.grid.querySelectorAll('.grid-stack-item').forEach((item) => {
                ROW_ATTRIBUTES.filter((name) => item.hasAttribute(name))
                    .forEach((name) => item.setAttribute(name, Number(item.getAttribute(name)) * this.rowsPerCell))
            })

            this.grid = GridStack.init(
                {
                    column: COLUMNS,
                    cellHeight: options.cellHeight / this.rowsPerCell,
                    margin: options.margin,
                    float: false,
                    animate: true,
                    staticGrid: !editable,
                    draggable: { cancel: 'input,textarea,button,select,option,a,.fi-dashboards-item-actions,.fi-dashboards-stat-resize' },
                    resizable: { handles: 'e, w', autoHide: false },
                    columnOpts: { breakpointForWindow: true, breakpoints: [{ w: options.stackBelow, c: 1 }] },
                },
                this.$refs.grid,
            )

            // Widgets load lazily and charts draw after mount, so heights settle after Gridstack measured them.
            this.observer = new ResizeObserver((entries) => {
                new Set(entries.map((entry) => entry.target.closest('.grid-stack-item'))).forEach((item) => item && this.fit(item))
            })

            this.$refs.grid.querySelectorAll('.fi-dashboards-item').forEach((item) => this.observer.observe(item))

            // Gridstack has no resize step, so a chosen height still snaps to whole cells on the finer rows.
            const moveNodeCheck = this.grid.engine.moveNodeCheck.bind(this.grid.engine)
            this.grid.engine.moveNodeCheck = (node, change) => moveNodeCheck(node, change.resizing ? { ...change, h: snapHeight(change.h, this.startHeight, this.rowsPerCell) } : change)

            this.reloadObserver = new MutationObserver(() => this.followReload())
            this.reloadObserver.observe(this.$root, { attributes: true, attributeFilter: ['class'] })
            this.stopWidgetDatesHook = Livewire.hook('commit', ({ component, commit, succeed, fail }) => this.followWidgetDates(component, commit, succeed, fail))

            this.grid.on('resizestart', (event, item) => {
                this.resizing = true
                this.startHeight = item.gridstackNode.h
            })
            this.grid.on('resize', (event, item) => this.followHeight(item))
            // Gridstack keeps one handler per event name, so resizestop does both jobs here.
            this.grid.on('resizestop', (event, item) => {
                this.resizing = false

                if (item.hasAttribute('data-fixed-height')) {
                    this.fill(item)
                }

                setTimeout(() => this.save())
            })
            this.grid.on('dragstop', () => setTimeout(() => this.save()))

            if (editable) {
                this.wide = window.matchMedia(`(min-width: ${options.stackBelow}px)`)
                this.lockWhenNarrow = () => {
                    this.grid.setStatic(!this.wide.matches)
                    this.$root.toggleAttribute('data-narrow', !this.wide.matches)
                }
                this.wide.addEventListener('change', this.lockWhenNarrow)
                this.lockWhenNarrow()
            }

            if (printing) {
                window.addEventListener('afterprint', () => {
                    const url = new URL(window.location.href)
                    url.searchParams.delete('print')
                    window.history.replaceState(window.history.state, '', url)
                }, { once: true })

                // Charts draw on the next frames after mount; printing earlier leaves them blank.
                setTimeout(() => window.print(), 1500)
            }

            if (editable) {
                this.$refs.grid.addEventListener('pointerdown', (event) => this.resizeCard(event))
                this.$refs.grid.addEventListener('keydown', (event) => this.resizeCardByKey(event))
            }
        },

        // Livewire drops its loading class as soon as the response lands, so a fast reload would only flash the skeleton.
        followReload() {
            const loading = this.$root.classList.contains('fi-dashboards-loading')

            if (loading && !this.reloadStartedAt) {
                this.reloadStartedAt = Date.now()
                clearTimeout(this.reloadTimer)
                this.$root.classList.add('fi-dashboards-reloading')
            }

            if (!loading && this.reloadStartedAt) {
                const remaining = MIN_RELOAD_MS - (Date.now() - this.reloadStartedAt)

                this.reloadStartedAt = null
                this.reloadTimer = setTimeout(() => this.$root.classList.remove('fi-dashboards-reloading'), Math.max(0, remaining))
            }
        },

        followWidgetDates(component, commit, succeed, fail) {
            const item = component.el.closest('.grid-stack-item')
            const calls = commit.calls.map((call) => call.method)
            const changesDates = calls.includes('applyFilters') || calls.includes('resetFilters')
                || (calls.includes('callMountedAction') && component.$wire.mountedActions?.at(-1)?.name === 'widgetDates')

            if (!item || !this.$root.contains(item) || !changesDates) {
                return
            }

            const startedAt = Date.now()
            const done = () => setTimeout(() => item.classList.remove('fi-dashboards-item-reloading'), Math.max(0, MIN_RELOAD_MS - (Date.now() - startedAt)))

            item.classList.add('fi-dashboards-item-reloading')
            succeed(done)
            fail(done)
        },

        followHeight(gridItem) {
            if (!gridItem.hasAttribute('data-adjustable-height') || gridItem.gridstackNode.h === this.startHeight) {
                return
            }

            gridItem.setAttribute('data-fixed-height', '')
            this.fill(gridItem)
        },

        // A chart or table with a chosen height stretches its plot or rows to the cell, which may shrink below its natural size.
        fill(gridItem) {
            const item = gridItem.querySelector('.fi-dashboards-item')
            const stretch = item.querySelector('.fi-wi-chart-frame, .fi-ta-content-ctn')

            if (!stretch) {
                return
            }

            const { marginTop, marginBottom } = this.grid.opts
            const cell = this.grid.getCellHeight(true)
            const chrome = item.offsetHeight - stretch.offsetHeight
            const height = gridItem.gridstackNode.h * cell - marginTop - marginBottom - chrome

            if (height < MIN_STRETCH && !this.resizing) {
                this.grid.update(gridItem, { h: Math.ceil((chrome + MIN_STRETCH + marginTop + marginBottom) / (cell * this.rowsPerCell)) * this.rowsPerCell })
            }

            const stretched = Math.max(MIN_STRETCH, height)

            if (Math.abs(stretch.offsetHeight - stretched) > 1) {
                stretch.style.height = `${stretched}px`
            }
        },

        fit(gridItem) {
            if (gridItem.hasAttribute('data-fixed-height')) {
                this.fill(gridItem)

                return
            }

            // Content that grows (a row detail opening) takes its new height at once; the slide is only for dragging and resizing.
            const animate = this.grid.opts.animate
            this.grid.setAnimation(false)
            this.grid.resizeToContent(gridItem)
            this.grid.setAnimation(animate)
        },

        resizeCard(event) {
            const handle = event.target.closest('.fi-dashboards-stat-resize')

            if (!handle || !this.wide?.matches) {
                return
            }

            event.preventDefault()
            event.stopPropagation()

            const column = handle.closest('.fi-grid-col')
            const cards = column.parentElement
            const gap = parseFloat(getComputedStyle(cards).columnGap) || 0
            const step = (cards.clientWidth + gap) / COLUMNS
            const startWidth = column.getBoundingClientRect().width
            const startX = event.clientX

            handle.setPointerCapture(event.pointerId)

            const move = (moveEvent) => {
                const span = Math.min(COLUMNS, Math.max(1, Math.round((startWidth + gap + moveEvent.clientX - startX) / step)))

                SPAN_VARIABLES.forEach((variable) => column.style.setProperty(variable, `span ${span} / span ${span}`))
            }

            const stop = () => {
                handle.removeEventListener('pointermove', move)
                handle.removeEventListener('pointerup', stop)
                handle.removeEventListener('pointercancel', stop)

                this.saveCardWidths(handle)
            }

            handle.addEventListener('pointermove', move)
            handle.addEventListener('pointerup', stop)
            handle.addEventListener('pointercancel', stop)
        },

        resizeCardByKey(event) {
            const handle = event.target.closest('.fi-dashboards-stat-resize')
            const change = { ArrowLeft: -1, ArrowRight: 1 }[event.key]

            if (!handle || !change || !this.wide?.matches) {
                return
            }

            event.preventDefault()

            const column = handle.closest('.fi-grid-col')
            const { gap, step } = this.cardMetrics(column.parentElement)
            const span = Math.min(COLUMNS, Math.max(1, this.spanOf(column, gap, step) + change))

            SPAN_VARIABLES.forEach((variable) => column.style.setProperty(variable, `span ${span} / span ${span}`))
            this.saveCardWidths(handle)
        },

        spanOf(column, gap, step) {
            const set = [...SPAN_VARIABLES]
                .reverse()
                .map((variable) => parseInt(column.style.getPropertyValue(variable).replace('span', ''), 10))
                .find((value) => value > 0)

            return set || Math.round((column.getBoundingClientRect().width + gap) / step)
        },

        cardMetrics(cards) {
            const gap = parseFloat(getComputedStyle(cards).columnGap) || 0

            return { gap, step: (cards.clientWidth + gap) / COLUMNS }
        },

        saveCardWidths(handle) {
            const cards = handle.closest('.fi-grid-col').parentElement
            const { gap, step } = this.cardMetrics(cards)
            const widths = {}

            cards.querySelectorAll(':scope > .fi-grid-col .fi-dashboards-stat').forEach((card) => {
                widths[card.dataset.cardKey] = this.spanOf(card.closest('.fi-grid-col'), gap, step)
            })

            this.$wire.saveCardWidths(Number(handle.closest('.grid-stack-item').getAttribute('gs-id')), widths)
        },

        save() {
            // Narrow screens stack every widget in one column; saving that would lose the desktop layout.
            if (this.grid.getColumn() !== COLUMNS) {
                return
            }

            this.$wire.saveDashboardLayout(storedLayout(this.grid.save(false), this.rowsPerCell, (node) => !this.$refs.grid.querySelector(`[gs-id="${node.id}"]`).hasAttribute('data-fixed-height')))
        },

        destroy() {
            this.wide?.removeEventListener('change', this.lockWhenNarrow)
            this.observer?.disconnect()
            this.reloadObserver?.disconnect()
            this.stopWidgetDatesHook?.()
            this.grid?.destroy(false)
        },
    }
}
