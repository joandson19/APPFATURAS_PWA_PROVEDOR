$(document).ready(function () {
    // === Variables ===
    const views = {
        'login-view': $('#login-view'),
        'contracts-view': $('#contracts-view'),
        'invoices-view': $('#invoices-view')
    };
    let currentContracts = [];
    let selectedContract = null;

    // === Service Worker Registration ===
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('Service Worker registered', reg))
            .catch(err => console.log('Service Worker registration failed', err));
    }

    // === PWA Install Logic ===
    let deferredPrompt;
    const pwaBanner = $('#pwa-install-banner');

    function showPwaBanner() {
        // Don't show if user dismissed it previously
        if (localStorage.getItem('pwa_dismissed')) return;
        // Don't show on desktop (handled by CSS too, but saves logic)
        if (window.innerWidth > 768) return;

        // Show after a small delay for better UX
        setTimeout(() => {
            pwaBanner.removeClass('hidden').css('display', 'flex');
        }, 3000);
    }

    // Check if app is already installed
    if (!window.matchMedia('(display-mode: standalone)').matches) {
        // Try manual check logic if needed, but rely mostly on event
        setTimeout(() => {
            // Optional: Force show if we want to be aggressive, but better wait for event
            // showPwaBanner(); 
        }, 2000);
    }

    $(window).on('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredPrompt = e.originalEvent;
        showPwaBanner();
    });

    $('#btn-pwa-install').on('click', function () {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                deferredPrompt = null;
                pwaBanner.addClass('hidden');
            });
        } else {
            // Fallback instruction
            alert('Para instalar: Toque no menu do navegador e escolha "Adicionar à Tela de Início".');
            pwaBanner.addClass('hidden');
        }
    });

    $('#btn-pwa-dismiss').on('click', function () {
        pwaBanner.addClass('hidden');
        localStorage.setItem('pwa_dismissed', 'true');
    });

    // === Fetch Settings (WhatsApp) ===
    $.getJSON('api/settings.php', function (settings) {
        if (settings.whatsapp_number) {
            const btn = $('#whatsapp-btn');
            btn.attr('href', `https://wa.me/${settings.whatsapp_number}`);
            btn.removeClass('hidden');
        }
    });

    // === Initialization ===
    var behavior = function (val) {
        return val.replace(/\D/g, '').length > 11 ? '00.000.000/0000-00' : '000.000.000-009';
    },
        options = {
            onKeyPress: function (val, e, field, options) {
                field.mask(behavior.apply({}, arguments), options);
            }
        };

    $('#cpf').mask(behavior, options);

    // === Auto-Login Logic (PWA Only) ===
    function isPwa() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function isMobile() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    }

    if (isPwa()) {
        const savedCpf = localStorage.getItem('saved_cpf');
        if (savedCpf) {
            $('#cpf').val(savedCpf);
            // Small delay to ensure everything is ready
            setTimeout(() => {
                $('#search-form').submit();
            }, 100);
        }
    }

    // === Navigation ===
    function showView(viewName) {
        $('.view').removeClass('active').addClass('hidden');
        views[viewName].removeClass('hidden');
        // Small timeout to allow display:block to apply before opacity transition
        setTimeout(() => {
            views[viewName].addClass('active');
        }, 10);
    }

    $('.btn-back').on('click', function () {
        const target = $(this).data('target');
        // If logged in via PWA auto-login, going back might just re-login.
        // But back button is usually just navigation. 
        showView(target);
    });

    // === Logout Logic ===
    $('.btn-logout').on('click', function () {
        if (isPwa()) {
            localStorage.removeItem('saved_cpf');
        }
        // Clear data
        currentContracts = [];
        $('#cpf').val('');
        showView('login-view');
        showToast('Você saiu da conta.', 'info');
    });

    // === Logic: Search Contracts ===
    $('#search-form').on('submit', function (e) {
        e.preventDefault();
        const cpf = $('#cpf').val();

        if (cpf.length < 11) {
            showToast('Por favor, digite um CPF ou CNPJ válido.', 'error');
            return;
        }

        setLoading(true);

        $.ajax({
            url: 'api/contracts.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ cpf: cpf }),
            success: function (response) {
                // SGP returns an array in response
                if (Array.isArray(response) && response.length > 0) {

                    // SUCCESS! Save CPF only if PWA
                    if (isPwa()) {
                        localStorage.setItem('saved_cpf', cpf);
                    } else {
                        // Ensure we don't accidentally keep it if they used PWA before then switched to browser (unlikely but safe)
                        localStorage.removeItem('saved_cpf');
                    }

                    // Filter logic could be here if needed
                    currentContracts = response; // Store raw response

                    if (currentContracts.length === 1) {
                        // User has only one contract, go straight to invoices
                        selectContract(currentContracts[0]);
                    } else {
                        renderContracts(currentContracts);
                        showView('contracts-view');
                    }
                } else {
                    showToast('Nenhum contrato encontrado para este CPF.', 'error');
                }
            },
            error: function (xhr) {
                try {
                    const err = JSON.parse(xhr.responseText);
                    showToast(err.message || 'Erro ao buscar contratos.', 'error');
                } catch (e) {
                    showToast('Erro de comunicação com o servidor.', 'error');
                }
            },
            complete: function () {
                setLoading(false);
            }
        });
    });

    function renderContracts(contracts) {
        const list = $('#contracts-list');
        list.empty();

        contracts.forEach((c, index) => {
            const statusClass = getStatusClass(c.status);

            const html = `
                <div class="card-item contract-card" data-index="${index}">
                    <div class="card-header">
                        <span class="card-title">Contrato ${c.contrato || c.id_contrato || 'N/A'}</span>
                        <span class="status-badge ${statusClass}">${c.status}</span>
                    </div>
                    <div class="card-subtitle">
                        ${c.endereco ? `${c.endereco.logradouro}, ${c.endereco.numero} - ${c.endereco.bairro}` : 'Endereço não informado'}
                    </div>
                    <div class="card-subtitle" style="margin-top: 5px; color: var(--primary-light)">
                        ${c.plano_nome || c.plano || 'Plano de Internet'}
                    </div>
                </div>
            `;
            list.append(html);
        });
    }

    $(document).on('click', '.contract-card', function () {
        const index = $(this).data('index');
        selectContract(currentContracts[index]);
    });

    function selectContract(contract) {
        selectedContract = contract;
        $('#contract-id-display').text(contract.contrato || contract.id_contrato); // Adjust field name based on actual API return
        $('#user-name-display').text(contract.razao_social || contract.nome || 'Cliente');

        // Check for Unlock Button
        const status = (contract.status || '').toLowerCase();
        // Assuming 'suspenso' or similar indicates blockage. 
        // User said "informe de pagamento ou promessa", usually for blocked services.
        if (status.includes('suspenso') || status.includes('bloqueado')) {
            $('#unlock-container').removeClass('hidden');
        } else {
            $('#unlock-container').addClass('hidden');
        }

        // Load specific client details for dashboard
        loadClientDetails(contract.contrato || contract.id_contrato);

        // Check for Maintenance
        checkMaintenance();

        // Auto-Sync Push Data (Update Phone if already subscribed)
        if (isPwa() || isMobile()) {
            setTimeout(() => subscribeUserToPush(true), 1000);
        }

        fetchInvoices(contract.contrato || contract.id_contrato);
    }

    function formatDateTime(dateString) {
        if (!dateString) return 'Data indefinida';
        // Handle "YYYY-MM-DD HH:mm:ss"
        try {
            const date = new Date(dateString.replace(/-/g, '/')); // Replace for Safari compatibility if needed
            return date.toLocaleString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit'
            });
        } catch (e) {
            return dateString;
        }
    }

    function checkMaintenance() {
        // $('#maintenance-container').empty(); // Old List Logic
        $.getJSON('api/maintenance.php', function (data) {
            if (data && data.length > 0) {
                // Priority Logic: Show the most critical or the first one
                // Simple sort: 'Indisponibilidade' > 'Instabilidade' > 'Aviso'
                const sorted = data.sort((a, b) => {
                    const sevA = (a.severidade || '').toLowerCase();
                    const sevB = (b.severidade || '').toLowerCase();

                    const score = s => {
                        if (s.includes('indisponibilidade') || s.includes('total')) return 3;
                        if (s.includes('instabilidade') || s.includes('parcial')) return 2;
                        return 1;
                    };
                    return score(sevB) - score(sevA);
                });

                showMaintenanceModal(sorted[0]);
            }
        });
    }

    function showMaintenanceModal(m) {
        const modal = $('#maintenance-modal');
        const overlay = modal; // In our CSS structure, #maintenance-modal is the overlay

        // Severity Logic
        let severityClass = 'modal-severity-critical'; // Default
        const titleLower = (m.titulo || '').toLowerCase();
        const sevLower = (m.severidade || '').toLowerCase();

        if (sevLower.includes('operacional') || titleLower.includes('aviso')) {
            severityClass = 'modal-severity-info';
        } else if (sevLower.includes('parcial') || titleLower.includes('instabilidade')) {
            severityClass = 'modal-severity-warning';
        }

        // Reset classes
        modal.removeClass('modal-severity-critical modal-severity-warning modal-severity-info').addClass(severityClass);

        // Content
        $('#modal-title').text(m.titulo);
        $('#modal-body').html(`
            <p>${m.mensagem}</p>
            <div class="maintenance-dates-modal">
                <div class="date-row">
                    <i class="fa-regular fa-clock"></i>
                    <span>Início: <strong>${formatDateTime(m.inicio)}</strong></span>
                </div>
                <div class="date-row">
                    <i class="fa-solid fa-flag-checkered"></i>
                    <span>Previsão: <strong>${formatDateTime(m.fim)}</strong></span>
                </div>
            </div>
        `);

        // Show
        modal.removeClass('hidden');
    }

    // Modal Close Handlers
    $('#btn-close-maintenance, #btn-ack-maintenance').on('click', function () {
        $('#maintenance-modal').addClass('hidden');
    });

    // Close on click outside (overlay)
    $('#maintenance-modal').on('click', function (e) {
        if (e.target === this) {
            $(this).addClass('hidden');
        }
    });

    // Load rich client details (Plan, Status, Connection)
    function loadClientDetails(contractId) {
        // Reset and hide first
        $('#dashboard-grid').addClass('hidden');
        $('#dash-plan').text('---');
        $('#dash-status').text('---');
        $('#dash-conn').text('---');

        $.ajax({
            url: 'api/client_details.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ contract_id: contractId }),
            success: function (response) {
                if (response.contratos && response.contratos.length > 0) {
                    const data = response.contratos[0];

                    // 1. Plan
                    $('#dash-plan').text(data.servico_plano || 'N/D');

                    // 2. Status
                    const statusText = data.contratoStatusDisplay || 'Desconhecido';
                    const elStatus = $('#dash-status');
                    elStatus.text(statusText);

                    // Color logic
                    elStatus.removeClass('status-ok status-warn status-danger');
                    if (statusText.toLowerCase().includes('ativo') || statusText.toLowerCase().includes('liberado')) {
                        elStatus.addClass('status-ok');
                    } else if (statusText.toLowerCase().includes('suspenso') || statusText.toLowerCase().includes('bloqueado')) {
                        elStatus.addClass('status-danger');
                    } else {
                        elStatus.addClass('status-warn');
                    }

                    // 3. Connection (Online/Offline)
                    const isOnline = data.servico_online === true || data.servico_online === 'true'; // API check
                    const elConn = $('#dash-conn');

                    if (isOnline) {
                        elConn.text('Online').removeClass('status-danger').addClass('status-ok');
                    } else {
                        elConn.text('Offline').removeClass('status-ok').addClass('status-danger');
                    }

                    $('#dashboard-grid').removeClass('hidden');
                }
            },
            error: function (err) {
                console.error("Erro ao carregar detalhes", err);
            }
        });
    }

    // === Logic: Fetch Invoices ===
    function fetchInvoices(contractId) {
        showView('invoices-view');
        $('#invoices-list').html('<div class="spinner" style="border-color: var(--primary); margin: 2rem auto;"></div>');

        $.ajax({
            url: 'api/invoices.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ contract_id: contractId }),
            success: function (response) {
                let invoices = [];
                if (response.links) {
                    invoices = response.links;
                } else if (Array.isArray(response)) {
                    invoices = response;
                }
                renderInvoices(invoices);
            },
            error: function () {
                $('#invoices-list').html('<p style="text-align:center; color: var(--text-muted)">Erro ao carregar faturas.</p>');
            }
        });
    }

    function renderInvoices(invoices) {
        const list = $('#invoices-list');
        list.empty();

        if (!invoices || invoices.length === 0) {
            list.html('<p style="text-align:center; color: var(--text-muted)">Nenhuma fatura em aberto.</p>');
            return;
        }

        // Logic to show/hide Push Button based on Environment (App vs Browser)
        if (isPwa() || isMobile()) {
            $('#btn-enable-push').removeClass('hidden');
            // Attempt valid Silent subscription if PWA
            if (Notification.permission === 'granted') {
                subscribeUserToPush(true);
            }
        } else {
            $('#btn-enable-push').addClass('hidden');
        }

        invoices.forEach(inv => {
            let status = 'Aberto';
            let statusClass = 'status-aberto';

            // Check dates for overdue
            const today = new Date();
            const vencimento = new Date(inv.vencimento_original || inv.vencimento);
            if (vencimento < today) {
                status = 'Vencido';
                statusClass = 'status-vencido';
            }

            const html = `
                <div class="card-item invoice-card ${statusClass}">
                    <div class="invoice-info">
                        <div>
                            <div class="card-subtitle">Vencimento</div>
                            <div class="card-title">${formatDate(inv.vencimento_original || inv.vencimento)}</div>
                        </div>
                        <div style="text-align: right">
                            <div class="card-subtitle">Valor</div>
                            <div class="invoice-value">R$ ${parseFloat(inv.valor).toFixed(2).replace('.', ',')}</div>
                        </div>
                    </div>
                    
                    ${inv.codigopix ? `
                    <div id="qrcode-container-${inv.id}" class="qrcode-wrapper hidden">
                        <div class="qrcode-label">Escaneie para pagar</div>
                    </div>
                    ` : ''}

                    <div class="invoice-actions">
                        ${inv.linhadigitavel ? `
                        <button class="btn-action btn-copy" data-code="${inv.linhadigitavel}" title="Copiar Linha Digitável">
                            <i class="fa-solid fa-barcode"></i>
                        </button>` : ''}
                        
                        ${inv.link ? `
                        <a href="${inv.link}" target="_blank" class="btn-action btn-download" title="Baixar PDF">
                            <i class="fa-solid fa-file-pdf"></i>
                        </a>` : ''}
                        
                        ${(inv.codigopix && inv.codigopix.length > 5) ? `
                        <button class="btn-action btn-pix-copy" data-code="${inv.codigopix}" title="Copiar Pix Copia e Cola">
                            <i class="fa-regular fa-copy"></i> Pix
                        </button>
                        <button class="btn-action btn-pix-qr" data-code="${inv.codigopix}" data-id="${inv.id}" title="Ver QR Code">
                            <i class="fa-solid fa-qrcode"></i>
                        </button>
                        ` : `
                        `}
                    </div>
                </div>
            `;
            list.append(html);
        });
    }

    // === Event Delegation for new buttons ===
    $(document).on('click', '.btn-pix-generate', function () {
        const btn = $(this);
        const invoiceId = btn.data('id');
        const contractId = btn.data('id-contrato');
        const actionsContainer = btn.parent();
        const cardItem = btn.closest('.card-item');

        const originalText = btn.html();
        btn.prop('disabled', true).html('<div class="spinner" style="width:16px;height:16px;border-width:2px;"></div> Gerando...');

        $.ajax({
            url: 'api/pix.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ invoice_id: invoiceId, contract_id: contractId }),
            success: function (response) {
                if (response.pix) {
                    showToast('Pix gerado com sucesso!', 'success');

                    // Add QR Code container logic if not exists (it won't because it was hidden/non-existent)
                    if (cardItem.find('.qrcode-wrapper').length === 0) {
                        $(`<div id="qrcode-container-${invoiceId}" class="qrcode-wrapper hidden"><div class="qrcode-label">Escaneie para pagar</div></div>`).insertBefore(actionsContainer);
                    }

                    // Replace button with Copia/Cola and QR
                    const newButtons = `
                        <button class="btn-action btn-pix-copy" data-code="${response.pix}" title="Copiar Pix Copia e Cola">
                            <i class="fa-regular fa-copy"></i> Pix
                        </button>
                        <button class="btn-action btn-pix-qr" data-code="${response.pix}" data-id="${invoiceId}" title="Ver QR Code">
                            <i class="fa-solid fa-qrcode"></i>
                        </button>
                    `;
                    btn.replaceWith(newButtons);
                } else {
                    showToast(response.msg || 'Erro ao gerar Pix.', 'error');
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function () {
                showToast('Erro de comunicação.', 'error');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $(document).on('click', '.btn-pix-copy', function () {
        const code = $(this).data('code');
        navigator.clipboard.writeText(code).then(() => {
            showToast('Pix Copia e Cola copiado!', 'success');
        });
    });

    $(document).on('click', '.btn-pix-qr', function () {
        const id = $(this).data('id');
        const code = $(this).data('code');
        const container = $(`#qrcode-container-${id}`);

        if (container.hasClass('hidden')) {
            container.removeClass('hidden');
            // Check if already generated to avoid duplicates (check for canvas/img)
            if (container.find('canvas').length === 0 && container.find('img').length === 0) {

                if (!code) {
                    showToast('Código Pix inválido ou vazio.', 'error');
                    return;
                }

                try {
                    // Determine target - append to container
                    // Ensure the container is visible/rendered before drawing
                    new QRCode(document.getElementById(`qrcode-container-${id}`), {
                        text: code,
                        width: 200, // Increased size for better density handling
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.L
                    });
                } catch (e) {
                    console.error('QR Gen Error (Local), switching to Fallback:', e);
                    // Fallback to Server-Side rendering if local JS fails (due to length/overflow)
                    container.empty(); // Clear potential partial render
                    const fallbackUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(code)}`;
                    container.append(`<img src="${fallbackUrl}" alt="QR Code Pix" style="width:200px;height:200px;margin:0 auto;display:block;">`);
                    container.append(`<div class="qrcode-label" style="margin-top:5px">Gerado via API</div>`);
                }
            }
        } else {
            container.addClass('hidden');
        }
    });



    // === Push Notification UI ===
    $('#btn-enable-push').on('click', function () {
        if (!isPwa() && !isMobile()) {
            showToast('Instale o App para receber notificações.', 'info');
            return;
        }

        const btn = $(this);
        // Visual feedback
        btn.prop('disabled', true).css('opacity', '0.5');

        if (Notification.permission === 'default') {
            Notification.requestPermission().then(function (result) {
                if (result === 'granted') {
                    showToast('Permissão concedida! Registrando...', 'info');
                    subscribeUserToPush();
                } else {
                    showToast('Permissão negada. Ative nas configurações.', 'error');
                    btn.prop('disabled', false).css('opacity', '1');
                }
            });
        } else if (Notification.permission === 'granted') {
            showToast('Sincronizando notificações...', 'info');
            subscribeUserToPush(); // Force sync
        } else {
            showToast('Permissão bloqueada.', 'error');
            btn.prop('disabled', false).css('opacity', '1');
        }

        setTimeout(() => {
            btn.prop('disabled', false).css('opacity', '1');
        }, 8000);
    });

    // Helpers
    function setLoading(isLoading) {
        const btn = $('#btn-search');
        if (isLoading) {
            btn.prop('disabled', true);
            btn.find('span').addClass('hidden');
            btn.find('.spinner').removeClass('hidden');
        } else {
            btn.prop('disabled', false);
            btn.find('span').removeClass('hidden');
            btn.find('.spinner').addClass('hidden');
        }
    }

    function showToast(msg, type = 'info') {
        const toast = $(`<div class="toast">${msg}</div>`);
        if (type === 'error') toast.css('background', '#ef4444');
        $('#toast-container').append(toast);
        setTimeout(() => {
            toast.remove();
        }, 3000);
    }

    // === Push Notification Logic ===
    function urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function subscribeUserToPush(isSilent = false) {
        // SECURITY UPDATE: Only allow Push in PWA mode OR Mobile Browser
        if (!isPwa() && !isMobile()) {
            if (!isSilent) console.log("Push disabled in browser info mode.");
            return;
        }

        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            if (!isSilent) showToast("Erro: Navegador não suporta Push.", "error");
            return;
        }

        // ... (rest of logic)

        navigator.serviceWorker.ready.then(function (registration) {
            $.getJSON('api/push_config.php?get_public=1', function (response) {
                if (response.publicKey) {
                    try {
                        const convertedVapidKey = urlBase64ToUint8Array(response.publicKey);

                        // Check existing
                        registration.pushManager.getSubscription().then(function (existingSub) {
                            if (existingSub) {
                                // REUSE existing subscription (Idempotent)
                                return Promise.resolve(existingSub);
                            } else {
                                // Create NEW subscription
                                const subscribeOptions = {
                                    userVisibleOnly: true,
                                    applicationServerKey: convertedVapidKey
                                };
                                return registration.pushManager.subscribe(subscribeOptions);
                            }
                        })
                            .then(function (pushSubscription) {
                                const cpf = $('#cpf').val() || localStorage.getItem('saved_cpf');

                                // Try to get phone from selectedContract or first contract
                                let phone = null;
                                if (selectedContract) {
                                    phone = selectedContract.telefone || selectedContract.celular || selectedContract.fone || selectedContract.whatsapp;
                                } else if (currentContracts && currentContracts.length > 0) {
                                    // Fallback to first contract's phone
                                    const c = currentContracts[0];
                                    phone = c.telefone || c.celular || c.fone || c.whatsapp;
                                }

                                if (cpf) {
                                    $.ajax({
                                        url: 'api/subscribe.php',
                                        method: 'POST',
                                        contentType: 'application/json',
                                        data: JSON.stringify({
                                            user_ref: cpf,
                                            subscription: pushSubscription,
                                            phone: phone // Send Phone
                                        }),
                                        success: function () {
                                            if (!isSilent) showToast('Notificações Ativas!', 'success');
                                        },
                                        error: function () {
                                            if (!isSilent) showToast('Erro ao salvar no servidor.', 'error');
                                        }
                                    });
                                }
                            })
                            .catch(function (e) {
                                console.error('Subscribe Error:', e);
                                if (!isSilent) showToast('Erro na inscrição.', 'error');
                            });

                    } catch (e) {
                        console.error("VAPID Key Error:", e);
                    }
                }
            });
        });
    }

    // Call subscription after successful login logic
    function trySubscribe() {
        // Deprecated by direct call in renderInvoices with silent=true
    }


    // Helpers
    function getStatusClass(status) {
        if (!status) return 'status-aberto';
        const s = status.toLowerCase();
        if (s.includes('ativo') || s.includes('pago')) return 'ativo';
        if (s.includes('cancel') || s.includes('suspenso')) return 'vencido'; // Red for cancelled/suspended
        return 'aberto';

    }

    function formatDate(dateString) {
        if (!dateString) return '--/--/----';
        const parts = dateString.split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    }

    // === Send Invoice Handlers (Delegated) ===
    $(document).on('click', '.btn-send-email', function () {
        sendInvoice('email', $(this));
    });

    $(document).on('click', '.btn-send-sms', function () {
        sendInvoice('sms', $(this));
    });

    function sendInvoice(type, btn) {
        if (!selectedContract) return;

        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<div class="spinner" style="width:16px;height:16px;border-width:2px;border-color:currentColor;border-top-color:transparent"></div> Enviando...');

        $.ajax({
            url: 'api/send_invoice.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                contract_id: selectedContract.contrato || selectedContract.id_contrato,
                type: type
            }),
            success: function (response) {
                try {
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.status === 1) {
                        showToast(res.msg || `Enviado por ${type.toUpperCase()} com sucesso!`, 'success');
                    } else {
                        showToast(res.msg || 'Erro ao enviar.', 'error');
                    }
                } catch (e) {
                    showToast('Erro ao processar resposta.', 'error');
                }
            },
            error: function () {
                showToast('Erro de comunicação.', 'error');
            },
            complete: function () {
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    }

    // === Unlock Handler ===
    $('#btn-unlock').on('click', function () {
        if (!selectedContract) return;
        const btn = $(this);
        const originalText = btn.html();
        btn.prop('disabled', true).html('<div class="spinner" style="width:16px;height:16px;border-width:2px;border-color:white;border-top-color:transparent"></div> Processando...');

        $.ajax({
            url: 'api/unlock.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ contract_id: selectedContract.contrato || selectedContract.id_contrato }),
            success: function (response) {
                // SGP response: { status: 1, msg: "...", liberado: true/false }
                try {
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.status === 1 || res.liberado === true) {
                        showToast(res.msg || 'Desbloqueio realizado com sucesso!', 'success');
                        $('#unlock-container').addClass('hidden'); // Hide after success
                    } else {
                        showToast(res.msg || 'Não foi possível realizar o desbloqueio.', 'error');
                    }
                } catch (e) {
                    showToast('Erro ao processar resposta do servidor.', 'error');
                }
            },
            error: function () {
                showToast('Erro de comunicação.', 'error');
            },
            complete: function () {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $(document).on('click', '.btn-copy', function () {
        const code = $(this).data('code');
        navigator.clipboard.writeText(code).then(() => {
            showToast('Código copiado!', 'success');
        });
    });
});
