
/**
 * Alpine.js drag-to-scroll component
 * Usage: <div x-data="dragScroll()" class="overflow-x-auto">...</div>
 *
 * Scrolling activates only when holding Shift key or using middle mouse button.
 * Without modifier, normal text selection works.
 *
 * Optional parameters:
 * - scrollSpeed: multiplier for scroll speed (default: 2)
 * - threshold: minimum pixels to move before scrolling starts (default: 5)
 */
export default function dragScroll(scrollSpeed: number = 2, threshold: number = 5) {
    return {
        isDragging: false,
        startX: 0,
        scrollLeft: 0,
        hasMoved: false,
        $el: null as any,

        init() {
            // Add global mouseup listener to catch mouseup outside element
            const stopDragHandler = () => this.stopDrag();
            document.addEventListener('mouseup', stopDragHandler);

            // Cleanup when component is destroyed
            this.$el._x_cleanups = this.$el._x_cleanups || [];
            this.$el._x_cleanups.push(() => {
                document.removeEventListener('mouseup', stopDragHandler);
            });
        },

        startDrag(e: MouseEvent) {
            // Only activate drag scroll when Shift is held or middle mouse button is used
            const isMiddleButton = e.button === 1;
            if (!e.shiftKey && !isMiddleButton) {
                return;
            }

            // Prevent dragging if clicking on interactive elements
            const target = e.target as HTMLElement;
            if (target.tagName === 'A' || target.tagName === 'BUTTON' || target.closest('a, button')) {
                return;
            }

            e.preventDefault();
            this.isDragging = true;
            this.startX = e.pageX - this.$el.offsetLeft;
            this.scrollLeft = this.$el.scrollLeft;
            this.hasMoved = false;
            this.$el.style.cursor = 'grabbing';
            this.$el.style.userSelect = 'none';
        },

        stopDrag() {
            if (!this.isDragging) return;

            this.isDragging = false;
            this.hasMoved = false;
            this.$el.style.cursor = '';
            this.$el.style.userSelect = '';
        },

        drag(e: MouseEvent) {
            if (!this.isDragging) return;

            e.preventDefault();
            const x = e.pageX - this.$el.offsetLeft;
            const walk = (x - this.startX) * scrollSpeed;

            if (Math.abs(walk) > threshold) {
                this.hasMoved = true;
                this.$el.scrollLeft = this.scrollLeft - walk;
            }
        },

        // Prevent click events if we've been dragging
        handleClick(e: MouseEvent) {
            if (this.hasMoved) {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    };
}
