export class DeletePositionCommand {
    constructor(url, csrfToken) {
        this.url = url;
        this.csrfToken = csrfToken;
    }

    async execute() {
        const response = await fetch(this.url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': this.csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            // On tente de récupérer le message d'erreur du JSON si possible
            const data = await response.json().catch(() => ({}));
            throw new Error(data.error || "Erreur lors de la suppression");
        }

        return await response.json();
    }
}
