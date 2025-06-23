document.addEventListener('DOMContentLoaded', function() {
    // --- DOM ELEMENTS ---
    const providerRadios = document.querySelectorAll('input[name="duplamtr_llm_provider"]');
    const modelGroups = {
        openai: document.getElementById('openai-models'),
        gemini: document.getElementById('gemini-models'),
        claude: document.getElementById('claude-models'),
        deepseek: document.getElementById('deepseek-models')
    };
    const keyDivs = {
        openai: document.getElementById('openai-key-div'),
        gemini: document.getElementById('gemini-key-div'),
        claude: document.getElementById('claude-key-div'),
        deepseek: document.getElementById('deepseek-key-div')
    };
    const chosenModelText = document.getElementById('chosen-model-text');
    const chosenModelTextPrefix = document.getElementById('chosen-model-text-prefix');
    const customModelInput = document.getElementById('duplamtr_custom_model');

    /**
     * Update the display of the chosen model.
     */
    function updateChosenModelDisplay() {
        let selectedProvider = document.querySelector('input[name="duplamtr_llm_provider"]:checked').value;
        let chosenModel = '';

        const customModelValue = customModelInput.value.trim();
        if (customModelValue) {
            chosenModel = customModelValue;
        } else {
            const modelRadio = document.querySelector('input[name="duplamtr_' + selectedProvider + '_model"]:checked');
            if (modelRadio) {
                chosenModel = modelRadio.value;
            }
        }
        if (chosenModel) {
            chosenModelTextPrefix.textContent = duplamtr_vars.chosen_model;
        } else {
            chosenModelTextPrefix.textContent = '';
        }
        chosenModelText.textContent = chosenModel;
    }

    /**
     * Toggle the visibility of the model and API key fields based on the selected provider.
     */
    function toggleVisibility() {
        let selectedProvider = document.querySelector('input[name="duplamtr_llm_provider"]:checked').value;

        for (const provider in modelGroups) {
            if (modelGroups[provider]) {
                modelGroups[provider].style.display = provider === selectedProvider ? 'block' : 'none';
            }
        }
        for (const provider in keyDivs) {
            if (keyDivs[provider]) {
                keyDivs[provider].style.display = provider === selectedProvider ? 'block' : 'none';
            }
        }
        updateChosenModelDisplay();
    }

    // --- EVENT LISTENERS ---
    providerRadios.forEach(radio => radio.addEventListener('change', toggleVisibility));
    
    const modelRadios = document.querySelectorAll('input[name^="duplamtr_"]');
    modelRadios.forEach(radio => radio.addEventListener('change', updateChosenModelDisplay));
    customModelInput.addEventListener('input', updateChosenModelDisplay);

    if (document.querySelector('input[name="duplamtr_llm_provider"]:checked')) {
        toggleVisibility(); // Initial check
    }
}); 