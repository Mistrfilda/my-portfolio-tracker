export default function datagrid(
	initialVisibleColumns: string[],
	defaultVisibleColumns: string[],
	saveColumnsUrl: string,
	columnsParameter: string,
) {
	return {
		filterOpen: false,
		columnOpen: false,
		columnsSaving: false,
		columnsError: false,
		visibleColumns: [...initialVisibleColumns],

		isColumnVisible(column: string): boolean {
			return this.visibleColumns.includes(column);
		},

		toggleColumn(column: string): void {
			const columns = this.isColumnVisible(column)
				? this.visibleColumns.filter((visibleColumn: string) => visibleColumn !== column)
				: [...this.visibleColumns, column];
			void this.saveColumns(columns);
		},

		resetColumns(): void {
			void this.saveColumns([...defaultVisibleColumns]);
		},

		async saveColumns(columns: string[]): Promise<void> {
			if (this.columnsSaving || columns.length === 0) {
				return;
			}

			const previousColumns = this.visibleColumns;
			this.visibleColumns = columns;
			this.columnsSaving = true;
			this.columnsError = false;
			const body = new URLSearchParams();
			columns.forEach((column) => body.append(`${columnsParameter}[]`, column));

			try {
				const response = await fetch(saveColumnsUrl, {
					method: 'POST',
					headers: { 'X-Requested-With': 'XMLHttpRequest' },
					body,
					keepalive: true,
				});
				if (!response.ok || (await response.json()).saved !== true) {
					throw new Error('Column preferences could not be saved.');
				}
			} catch {
				this.visibleColumns = previousColumns;
				this.columnsError = true;
			} finally {
				this.columnsSaving = false;
			}
		},
	};
}
