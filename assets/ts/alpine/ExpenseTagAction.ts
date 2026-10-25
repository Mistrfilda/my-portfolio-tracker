import naja from 'naja';

export default function expenseTagAction(expenseId: string, handleLink: string) {
	return {
		mainTagId: '',
		saving: false,
		errorMessage: '',

		async click(tagId?: string): Promise<void> {
			const selectedTagId = tagId ?? this.mainTagId;
			if (selectedTagId === '' || this.saving) {
				return;
			}

			const requestLink = handleLink
				.replace('replaceTagId', encodeURIComponent(selectedTagId))
				.replace('replaceExpenseId', encodeURIComponent(expenseId));
			this.saving = true;
			this.errorMessage = '';

			try {
				await naja.makeRequest('GET', requestLink, null, {
					history: false,
					responseType: 'json',
					unique: false,
				});
			} catch {
				this.errorMessage = 'Změnu tagu se nepodařilo uložit. Zkuste to znovu.';
			} finally {
				this.saving = false;
			}
		},
	};
}
