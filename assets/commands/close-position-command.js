export class ClosePositionCommand {
    constructor(url, formData) {
        this.url = url;
        this.formData = formData;
    }

    async execute() {
        const response = await fetch(this.url, {
            method: 'POST',
            body: this.formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        if (!response.ok) {
            throw new Error("Erreur réseau");
        }

        return await response.json();
    }
}
