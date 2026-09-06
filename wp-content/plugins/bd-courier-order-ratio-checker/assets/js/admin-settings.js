document.addEventListener('DOMContentLoaded', function () {
    new Vue({
        el: '#vue-settings',
        data: {
            apiToken: bdCourierSettings.apiToken,
            saving: false,
            successMessage: '',
            checkingConnection: false,
            connectionStatus: null,
            planInfo: null,
            loadingPlan: false,
            showPassword: false,
            showRawResponse: false
        },
        methods: {
            onFocus(event) {
                event.target.closest('.bdc-input-wrapper')?.classList.add('bdc-input-focused');
            },
            onBlur(event) {
                event.target.closest('.bdc-input-wrapper')?.classList.remove('bdc-input-focused');
            },
            togglePassword() {
                this.showPassword = !this.showPassword;
                var input = document.getElementById('mg-api-token');
                if (input) {
                    input.type = this.showPassword ? 'text' : 'password';
                }
            },
            toggleRawResponse() {
                this.showRawResponse = !this.showRawResponse;
            },
            saveSettings() {
                this.saving = true;
                this.successMessage = '';
                var data = {
                    action: 'save_courier_settings',
                    apiToken: this.apiToken,
                    _wpnonce: bdCourierSettings.nonce
                };
                jQuery.post(bdCourierSettings.ajaxurl, data, (response) => {
                    this.saving = false;
                    this.successMessage = response.success ? 'Settings saved successfully.' : 'Error saving settings.';
                    setTimeout(() => { this.successMessage = ''; }, 3000);
                });
            },
            checkConnection() {
                if (!this.apiToken) {
                    this.connectionStatus = {
                        type: 'error',
                        message: 'Please enter an API token first.'
                    };
                    return;
                }
                this.checkingConnection = true;
                this.connectionStatus = null;
                var data = {
                    action: 'check_api_connection',
                    _wpnonce: bdCourierSettings.nonce
                };
                jQuery.post(bdCourierSettings.ajaxurl, data, (response) => {
                    this.checkingConnection = false;
                    if (response.success) {
                        this.connectionStatus = {
                            type: 'success',
                            message: response.data.message || 'API connection successful!',
                            rawResponse: response.data.raw_response || null,
                            httpCode: response.data.http_code || null
                        };
                        // Automatically fetch plan info after successful connection
                        this.getPlanInfo();
                    } else {
                        var errorData = response.data || {};
                        var errorMessage = errorData.message || 'Failed to connect to API.';
                        this.connectionStatus = {
                            type: 'error',
                            message: errorMessage,
                            rawResponse: errorData.raw_response || null,
                            httpCode: errorData.http_code || null,
                            fullResponse: errorData
                        };
                    }
                });
            },
            getPlanInfo() {
                if (!this.apiToken) {
                    return;
                }
                this.loadingPlan = true;
                var data = {
                    action: 'get_plan_info',
                    _wpnonce: bdCourierSettings.nonce
                };
                jQuery.post(bdCourierSettings.ajaxurl, data, (response) => {
                    this.loadingPlan = false;
                    if (response.success && response.data.data) {
                        this.planInfo = response.data.data;
                    } else if (!response.success) {
                        var errorData = response.data || {};
                        var errorMessage = errorData.message || 'Failed to retrieve plan information.';
                        if (errorData.raw_response) {
                            errorMessage += '\n\nOriginal API Response:\n' + errorData.raw_response;
                        }
                        if (errorData.http_code) {
                            errorMessage += '\n\nHTTP Status Code: ' + errorData.http_code;
                        }
                        alert('Error: ' + errorMessage);
                    }
                });
            },
            refreshPlanInfo() {
                this.getPlanInfo();
            }
        },
        mounted() {
            // Auto-load plan info if API token exists
            if (this.apiToken) {
                this.getPlanInfo();
            }
        }
    });
});
