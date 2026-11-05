import { GridStack } from 'gridstack'

const COLUMNS = 12

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
                    draggable: { cancel: 'input,textarea,button,select,option,a,.fi-dashboards-item-actions' },
                    resizable: { handles: 'e, w', autoHide: false },
                    columnOpts: { breakpointForWindow: true, breakpoints: [{ w: 1024, c: 1 }] },
                },
                this.$refs.grid,
            )

            // Widgets load lazily and charts draw after mount, so heights settle after Gridstack measured them.
            this.observer = new ResizeObserver((entries) => {
                entries.forEach((entry) => this.grid.resizeToContent(entry.target.closest('.grid-stack-item')))
            })

            this.$refs.grid.querySelectorAll('.fi-dashboards-item').forEach((item) => this.observer.observe(item))

            this.grid.on('dragstop resizestop', () => setTimeout(() => this.save()))
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
            this.observer?.disconnect()
            this.grid?.destroy(false)
        },
    }
}
