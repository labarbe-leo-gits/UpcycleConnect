
const Moderator = {

    async check(text) {
        if (!text || text.trim().length < 2) {
            return {
                flagged: false,
                reasons: [],
                flaggedWords: [],
                flaggedSentences: [],
                details: ''
            };
        }

        try {
            const response = await fetch('/pages/common/moderator-check-api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ text: text.trim() })
            });

            if (!response.ok) {
                console.error('[Moderator] API error:', response.status);
                return {
                    flagged: false,
                    reasons: [],
                    flaggedWords: [],
                    flaggedSentences: [],
                    details: 'Could not verify content'
                };
            }

            const result = await response.json();

            let details = '';
            if (result.flagged) {
                details = 'Content flagged: ' + (result.reasons?.join(', ') || 'Policy violation');
                if (result.flaggedWords?.length > 0) {
                    details += '\nFlagged words: ' + result.flaggedWords.join(', ');
                }
            }

            return {
                flagged: result.flagged || false,
                reasons: result.reasons || [],
                flaggedWords: result.flaggedWords || [],
                flaggedSentences: result.flaggedSentences || [],
                details: details
            };
        } catch (err) {
            console.error('[Moderator] Check failed:', err);
            return {
                flagged: false,
                reasons: [],
                flaggedWords: [],
                flaggedSentences: [],
                details: 'Could not verify content'
            };
        }
    },

    async checkAndThrow(text) {
        const result = await this.check(text);
        if (result.flagged) {
            throw new Error(result.details || 'Content violates moderation policy');
        }
        return result;
    },

    async checkFields(fields) {
        const errors = {};
        
        for (const [fieldName, fieldText] of Object.entries(fields)) {
            const result = await this.check(fieldText);
            if (result.flagged) {
                errors[fieldName] = result.details || 'Content violates moderation policy';
            }
        }

        return {
            valid: Object.keys(errors).length === 0,
            errors: errors
        };
    }
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = Moderator;
}
