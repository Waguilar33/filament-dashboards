import { GridStack } from 'gridstack'

const COLUMNS = 12

const SPAN_VARIABLES = ['--col-span-csm', '--col-span-ncmd', '--col-span-cxl', '--col-span-nclg']

export default function dashboardGrid({ editable }) {
    return {
        grid: null,

        observer: null,

        init() {
            this.grid = GridStack.init(
                {
                    column: COLUMNS,
                    cellHeight: 40,
                    margin: 8,
                    float: false,
                    animate: true,
                    sizeToContent: true,
                    staticGrid: !editable,
                    draggable: { cancel: 'input,textarea,button,select,option,a,.fi-dashboards-item-actions,.fi-dashboards-stat-resize' },
                    resizable: { handles: 'e, w', autoHide: false },
                    columnOpts: { breakpointForWindow: true, breakpoints: [{ w: 1024, c: 1 }] },
                },
                this.$refs.grid,
            )

            // Widgets load lazily and charts draw after mount, so heights settle after Gridstack measured them.
            this.observer = new ResizeObserver((entries) => {
                new Set(entries.map((entry) => entry.target.closest('.grid-stack-item'))).forEach((item) => item && this.fit(item))
            })

            this.$refs.grid.querySelectorAll('.fi-dashboards-item').forEach((item) => this.observer.observe(item))

            this.grid.on('dragstop resizestop', () => setTimeout(() => this.save()))

            if (editable) {
                this.wide = window.matchMedia('(min-width: 1024px)')
                this.lockWhenNarrow = () => this.grid.setStatic(!this.wide.matches)
                this.wide.addEventListener('change', this.lockWhenNarrow)
                this.lockWhenNarrow()
            }

            if (editable) {
                this.$refs.grid.addEventListener('pointerdown', (event) => this.resizeCard(event))
                this.$refs.grid.addEventListener('keydown', (event) => this.resizeCardByKey(event))
            }
        },

        fit(gridItem) {
            const item = gridItem.querySelector('.fi-dashboards-item')
            const card = this.card(item)

            if (card) {
                card.style.minHeight = ''
            }

            this.grid.resizeToContent(gridItem)

            if (!card) {
                return
            }

            Array.from(card.children).forEach((child) => this.observer.observe(child))

            const { marginTop, marginBottom } = this.grid.opts
            const extra = gridItem.gridstackNode.h * this.grid.getCellHeight(true) - marginTop - marginBottom - item.offsetHeight

            if (extra > 0) {
                card.style.minHeight = `${card.offsetHeight + extra}px`
            }
        },

        // Gridstack sizes a cell from its content, so the widget's card, or its innermost layout without one, is stretched to fill the cell's last partial row.
        card(item) {
            const box = item.getBoundingClientRect()
            const walker = document.createTreeWalker(item, NodeFilter.SHOW_ELEMENT)

            while (walker.nextNode()) {
                const element = walker.currentNode
                const { width, height } = element.getBoundingClientRect()

                if (width >= box.width - 1 && height >= box.height * 0.8 && getComputedStyle(element).backgroundColor !== 'rgba(0, 0, 0, 0)') {
                    return element
                }
            }

            let element = item

            while (element) {
                const shown = Array.from(element.children).filter((child) => child.offsetHeight > 0)

                if (shown.length !== 1) {
                    return element === item ? null : element
                }

                element = shown[0]
            }

            return null
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

            this.$wire.saveDashboardLayout(
                this.grid.save(false).map((node) => ({ id: Number(node.id), x: node.x, y: node.y, w: node.w, h: node.h })),
            )
        },

        destroy() {
            this.wide?.removeEventListener('change', this.lockWhenNarrow)
            this.observer?.disconnect()
            this.grid?.destroy(false)
        },
    }
}
