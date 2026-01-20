# 📱 FaturaFacil PWA

Este é o projeto **FaturaFacil**, um PWA (Progressive Web App) desenvolvido para facilitar o acesso de clientes de provedores que usam o SGP como ERP. Nele o clientes em acesso a faturas, contratos e informações de manutenção.

O sistema integra diretamente com o SGP (Sistema de Gestão de Provedor) via API.

## ✨ Funcionalidades Principais

-   **Autenticação Simplificada:** Login via CPF/CNPJ.
-   **Segunda Via de Fatura:** Visualização de faturas em aberto e vencidas.
-   **Pagamento via Pix:** Geração de QR Code e "Copia e Cola" instantâneo.
-   **Desbloqueio de Confiança:** Liberação temporária de sinal (se permitido pelo plano).
-   **Alertas de Manutenção:** Popups automáticos informando sobre instabilidades na rede (dados do SGP).
-   **Push Notifications:** Notificação de faturas e avisos diretamente no celular.

---

## 📋 Requisitos do Servidor

Para rodar este projeto com 100% de funcionalidade, seu servidor precisa atender aos seguintes requisitos:

-   **PHP 7.4 ou superior**
-   **Módulos PHP Obrigatórios:**
    -   `openssl` (Para geração de chaves VAPID do Push)
    -   `curl` (Para comunicar com a API do SGP)
    -   `pdo_sqlite` (Banco de dados local para inscrições Push)
    -   `json` (Manipulação de dados API)
-   **HTTPS (SSL):** Obrigatório para o funcionamento do Service Worker (PWA) e Push Notifications.
-   Permissão de Escrita: O PHP precisa ter permissão para criar arquivos na pasta raiz ou `/api` (para `notifications.db`, `vapid_keys.json` e logs).

---

## 🚀 Instalação

1.  **Clone ou Copie os Arquivos**
    Copie todos os arquivos do projeto para a pasta pública do seu servidor web (ex: `public_html/app`).

2.  **Configuração do Ambiente**
    O arquivo de configuração principal é o `.config.php` (oculto por segurança).
    
    *Se ele não existir, copie o modelo abaixo e salve como `.config.php` na raiz:*

    ```php
    <?php
    return [
        // Credenciais da API do SGP
        'sgp_url' => 'https://seu-sgp.com.br',
        'sgp_token' => 'seu-token-api-sgp', 
        'sgp_app_name' => 'nome-do-app',
        
        // WhatsApp para Suporte (Formato Internacional: 5575...)
        'whatsapp_number' => '5575999999999',
        
        // Token de Segurança para Disparo de Push (Webhook)
        // Defina uma senha forte aqui. Ela será usada na URL de disparo.
        'webhook_token' => '123456' 
    ];
    ```

3.  **Permissões de Pasta**
    Certifique-se de que a pasta `api/` e a raiz tenham permissão de escrita (`chmod 755` ou `775` geralmente funcionam, dependendo do usuário do Apache/Nginx), pois o sistema criará o banco de dados `notifications.db` automaticamente no primeiro acesso.

---

## 🔔 Push Notifications (Configuração)

O sistema utiliza Web Push Notifications (VAPID). As chaves de criptografia são geradas **automaticamente** pelo servidor na primeira execução.

**Compatibilidade:**
-   **Android/iOS (App Instalado):** Funciona 100%.
-   **Navegador Móvel (Chrome/Safari):** Funciona mesmo sem instalar o App.
-   **Desktop (PC):** Desativado intencionalmente.

1.  **Geração de Chaves:**
    Acesse a URL: `https://seu-dominio.com/api/push_config.php?get_public=1`
    Se retornar um JSON com `publicKey`, a configuração automática funcionou.
    *O arquivo `api/vapid_keys.json` será criado automaticamente.*

---

## 📡 Como Disparar Notificações (Webhook)

O sistema possui um **Webhook Seguro** para integração com o SGP ou outros sistemas.

**endpoint:** `https://seu-dominio.com/api/webhook_sgp.php`

### Parâmetros Obrigatórios (GET ou POST)

| Parâmetro | Descrição | Exemplo |
| :--- | :--- | :--- |
| `token` | **Senha de segurança** definida no `.config.php` | `123456` |
| `contrato` | ID do contrato ou CPF (sem pontuação) | `1234`, `00011122233` |
| `telefone` | Telefone do cliente (com ou sem DDD/DDI) | `75999999999` ou `5575...` |
| `target` | Use `all` para enviar para todos | `all` |

> **Nota:** É obrigatório informar `contrato` OU `telefone` OU `target=all`.
> O sistema remove automaticamente o prefixo `55` se enviado pelo SGP (ex: `5575` vira `75`).

### Parâmetros Opcionais

| Parâmetro | Descrição | Padrão |
| :--- | :--- | :--- |
| `title` | Título da notificação | "Nova Mensagem" |
| `msg` | Corpo da mensagem | "Você tem uma nova notificação." |
| `url` | Link para abrir ao clicar | `/` |

### Exemplos de Uso

**1. Enviar para um cliente específico (via Navegador/GET):**
```
https://seu-dominio.com/api/webhook_sgp.php?token=123456&contrato=1420&title=Fatura&msg=Sua+fatura+vence+hoje!
```

**2. Enviar para todos (Broadcast):**
```
https://seu-dominio.com/api/webhook_sgp.php?token=123456&contrato=all&title=Aviso&msg=Manutenção+programada+às+14h
```

**Retornos Possíveis:**
- `200 OK`: Sucesso (`{"status": "Processed", ...}`)
- `403 Forbidden`: Token inválido ou ausente.
- `200 OK` (com erro no JSON): Usuário não encontrado no banco de dados de push.

---

---

## 🎨 Personalização da Marca (Logo)

O sistema inclui uma ferramenta automática para ajustar sua logomarca aos formatos exigidos pelo PWA (Ícones quadrados e Logo retangular).

1.  Tenha sua logo original em mãos (ex: `original.png`).
2.  Execute o script de ferramentas via terminal:
    ```bash
    php tools/generate_assets.php "caminho/para/sua_logo.png"
    ```
3.  O script criará automaticamente:
    -   `assets/img/logo.png`: Redimensionada para o cabeçalho.
    -   `assets/img/logo_icon.png`: Centralizada com padding transparente (512x512) para ícone de app.

---

## 📱 PWA (Instalação no Celular)

Para que o botão "Instalar App" apareça:
1.  O site deve estar em **HTTPS**.
2.  O arquivo `manifest.json` deve estar acessível na raiz.
3.  O `sw.js` (Service Worker) deve carregar sem erros.

### Como Forçar Atualização nos Usuários
Se você alterar arquivos `js` ou `css`, os usuários antigos **não verão a mudança imediatamente** por causa do cache.
Para forçar a atualização:
1. Abra o arquivo `sw.js`.
2. Altere a primeira linha: `const CACHE_NAME = 'faturafacil-v2.3';` (Incremente o número).
3. Salve. O sistema fará a limpeza automática do cache antigo no próximo acesso do usuário.

---

## 🛠️ Manutenção e Arquivos Importantes

-   `api/logs/`: O sistema pode gerar logs de erro em arquivos `.txt` dentro da pasta api em caso de falhas críticas.
-   `api/logs/`: O sistema pode gerar logs de erro em arquivos `.txt` dentro da pasta api em caso de falhas críticas.
-   `notifications.db`: Banco de dados SQLite contendo os inscritos. **Faça backup deste arquivo** se mudar de servidor.
    > **Nota sobre Auto-Limpeza:** O sistema detecta automaticamente inscrições inválidas (Erro 410 Gone) durante o envio e as remove do banco para manter a performance e higiene dos dados.
-   `.config.php`: Suas senhas. **Nunca** compartilhe ou coloque em repositório público.
