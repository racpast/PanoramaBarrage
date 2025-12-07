// --- 全局变量定义 ---
var opt, tp;
const container = document.getElementById('barrageContainer');
const textInput = document.getElementById('textInput');
const sendButton = document.getElementById('sendButton');
const userArea = document.getElementById('userArea');
const hideBarragesCheckbox = document.getElementById('hideBarragesCheckbox');
const controlPanel = document.getElementById('controlPanel');
const panelToggleButton = document.getElementById('panelToggleButton');
const contextMenu = document.getElementById('barrageContextMenu');
const reportMenuItem = document.getElementById('reportMenuItem');
const copyMenuItem = document.getElementById('copyMenuItem');
let currentUser = { isLoggedIn: false, username: null };
let latestBarrageId = 0;
let historicalBarrages = [];
let lines = [];
let isInitialLoading = false;
let currentBarrageElement = null;
let currentBarrageContent = '';
let BARRAGE_MAX_LENGTH = 25;
let currentTextColor = '#ffffff';
let currentBgColor = '#000000';

// --- 颜色选择器初始化 ---
const pickrOptions = {
theme: 'classic', // 主题
default: '#FFFFFF', // 默认颜色
position: 'top-middle', // 弹出位置
components: {
    preview: true,
    opacity: false,
    hue: true,
    interaction: {
        hex: false,
        rgba: false,
        hsla: false,
        hsva: false,
        cmyk: false,
        input: true,
        clear: false,
        save: true
    }
}
};

// 初始化文字颜色选择器
const textColorPicker = Pickr.create({
...pickrOptions, // 使用通用配置
el: '#text-color-picker',
default: currentTextColor
});

// 初始化背景颜色选择器
const bgColorPicker = Pickr.create({
...pickrOptions,
el: '#bg-color-picker',
default: currentBgColor
});

// 当用户点击保存颜色时，我们更新变量
textColorPicker.on('save', (color, instance) => {
currentTextColor = color.toHEXA().toString(0); // 获取 #RRGGBB 格式的HEX
instance.hide(); // 保存后自动隐藏
});

bgColorPicker.on('save', (color, instance) => {
currentBgColor = color.toHEXA().toString(0);
instance.hide();
});

// --- 核心生命周期与数据获取 ---
window.onload = function() {
    opt = { container: 'panoramaContainer', url: 'img/example.jpg', lables: [], widthSegments: 100, heightSegments: 80, pRadius: 1000, minFocalLength: 6, maxFocalLength: 100 };
    tp = new tpanorama(opt);
    tp.init();
    checkLoginStatus();
    loadInitialBarrages();
    setInterval(fetchNewBarrages, 3000);
    setInterval(() => { if (!isInitialLoading && container.children.length < 15) { createGhostBarrage(); } }, 2000);
    const urlParams = new URLSearchParams(window.location.search);
    const token = urlParams.get('token');
    if (token) {
        document.getElementById('reset-token').value = token;
        showModal('resetPasswordModal');
    }
};

async function checkLoginStatus() {
    try {
        const response = await fetch('/api/me.php?t=' + new Date().getTime());
        const data = await response.json();
        
        // 获取后端的配置
        if (data.config && data.config.barrageMaxLength) {
            BARRAGE_MAX_LENGTH = data.config.barrageMaxLength;
            textInput.maxLength = BARRAGE_MAX_LENGTH; // 动态设置输入框的最大长度
        }
        
        if (data.isLoggedIn) {
            currentUser = data.user;
            currentUser.isLoggedIn = true;
        } else {
            currentUser = { isLoggedIn: false, username: null };
        }
    } catch (error) {
        console.error('检查登录状态失败：', error);
        currentUser = { isLoggedIn: false, username: null };
    }
    updateUI();
    // 页面加载后，手动更新一次计数器
    updateCharCounter(textInput.value.length);
}

function updateCharCounter(currentLength) {
    const counter = document.querySelector('.char-counter');
    if (counter) {
        counter.textContent = `${currentLength} / ${BARRAGE_MAX_LENGTH}`;
        const warningThreshold = BARRAGE_MAX_LENGTH * 0.8;
        if (currentLength >= BARRAGE_MAX_LENGTH) {
            counter.classList.add('error');
            counter.classList.remove('warning');
        } else if (currentLength >= warningThreshold) {
            counter.classList.add('warning');
            counter.classList.remove('error');
        } else {
            counter.classList.remove('warning');
            counter.classList.remove('error');
        }
    }
}

async function loadInitialBarrages() {
    try {
        isInitialLoading = true;
        const response = await fetch('/api/barrages.php');
        const barrages = await response.json();
        if (barrages && barrages.length > 0) {
            latestBarrageId = barrages[barrages.length - 1].id;
            historicalBarrages = barrages;
            const initialBatch = barrages.slice(-20);
            const totalLoadTime = initialBatch.length * 1500 + 1000;
            initialBatch.forEach((barrage, index) => {
                setTimeout(() => createBarrage(barrage), index * 1500 + Math.random() * 500);
            });
            setTimeout(() => {
                isInitialLoading = false;
                console.log("初始弹幕加载完成。");
            }, totalLoadTime);
        } else {
            isInitialLoading = false;
        }
    } catch (error) {
        console.error('加载初始弹幕失败：', error);
        isInitialLoading = false;
    }
}

async function fetchNewBarrages() {
    if (latestBarrageId === 0) return;
    try {
        const response = await fetch(`/api/barrages.php?since_id=${latestBarrageId}`);
        const newBarrages = await response.json();
        if (newBarrages && newBarrages.length > 0) {
            latestBarrageId = newBarrages[newBarrages.length - 1].id;
            historicalBarrages.push(...newBarrages);
            newBarrages.forEach(createBarrage);
        }
    } catch (error) { console.error('获取新弹幕失败：', error); }
}

// --- UI 更新与交互逻辑 ---
function createWaveLabel(text) {
    return text.split('').map((char, index) =>
        `<span class="label-char" style="--index: ${index}">${char === ' ' ? '&nbsp;' : char}</span>`
    ).join('');
}

function updateUI() {
    const guestAvatarUrl = 'img/guest-avatar.png';
    const textInputLabel = document.getElementById('textInputLabel');

    if (currentUser.isLoggedIn) {
        let avatarHtml;
        if (currentUser.avatar && !currentUser.avatar.includes('default-avatar.png')) {
            avatarHtml = `<div class="avatar" style="background-image: url('${currentUser.avatar}')"></div>`;
        } else {
            avatarHtml = generateInitialAvatar(currentUser.username, 44);
        }
        userArea.innerHTML = `<div class="avatar-container">${avatarHtml}<div class="menu"><ul><li onclick="document.getElementById('avatarUploadInput').click()"><i class="fas fa-camera" style="width:16px;"></i>上传头像</li><li onclick="showModal('changePasswordModal')"><i class="fas fa-shield-alt" style="width:16px;"></i>修改密码</li><li onclick="handleLogout()"><i class="fas fa-sign-out-alt" style="width:16px;"></i>退出登录</li></ul></div></div>`;
        if (textInputLabel) textInputLabel.innerHTML = createWaveLabel('输入你的弹幕...');
        textInput.disabled = false;
        sendButton.disabled = false;
    } else {
        userArea.innerHTML = `<div class="avatar-container"><div class="avatar" style="background-image: url('${guestAvatarUrl}')"></div><div class="menu"><ul><li onclick="showModal('loginModal')"><i class="fas fa-sign-in-alt" style="width:16px;"></i>登录</li><li onclick="showModal('registerModal')"><i class="fas fa-user-plus" style="width:16px;"></i>注册</li></ul></div></div>`;
        if (textInputLabel) textInputLabel.innerHTML = createWaveLabel('请先登录再发送弹幕');
        textInput.disabled = true;
        sendButton.disabled = true;
    }
}

async function handleLogout() {
    await fetch('/api/logout.php', { method: 'POST' });
    currentUser = { isLoggedIn: false, username: null };
    textInput.value = '';
    updateCharCounter(0);
    updateUI();
}

function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    const messageBox = modal.querySelector('.message-box');
    if (messageBox) {
        messageBox.style.display = 'none';
        messageBox.textContent = '';
    }
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('active'); }, 10);
}

function hideModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    // 在隐藏模态框时，查找其中的表单并重置它
    const form = modal.querySelector('form');
    if (form) {
        form.reset();
        // 如果有消息框，也一并隐藏
        const messageBox = modal.querySelector('.message-box');
        if (messageBox) {
            messageBox.style.display = 'none';
        }
    }

    modal.classList.add('closing');
    modal.classList.remove('active');
    modal.addEventListener('animationend', function handler() {
        modal.style.display = 'none';
        modal.classList.remove('closing');
    }, { once: true });
}

function showMessage(boxId, message, isSuccess) {
    const box = document.getElementById(boxId);
    if (!box) return;
    box.textContent = message;
    box.className = 'message-box';
    box.classList.add(isSuccess ? 'success' : 'error');
    box.style.display = 'block';
}

// --- 弹幕系统核心逻辑 ---
async function sendBarrage() {
    if (!currentUser.isLoggedIn) { alert('登录后才能发送弹幕！'); return; }
    const text = textInput.value.trim();
    if (!text) return;
    const formData = new FormData();
    formData.append('text', text);
    formData.append('text_color', currentTextColor);
    formData.append('bg_color', currentBgColor);
    formData.append('mode', document.querySelector('input[name="mode"]:checked').value);
    formData.append('speed', document.querySelector('input[name="speed"]:checked').value);
    try {
        const response = await fetch('/api/barrages.php', { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) { textInput.value = ''; updateCharCounter(0); }
        else { alert(result.message); }
    } catch (error) { console.error('发送弹幕失败：', error); }
}

function createBarrage(barrageData) {
    const { id, avatar_url, username, content, color, bg_color, mode, speed } = barrageData;
    const div = document.createElement('div');
    div.className = 'barrage';
    div.dataset.id = id;
    let avatarHtml = (avatar_url && !avatar_url.includes('default-avatar.png'))
        ? `<div class="barrage-avatar" style="background-image: url('${avatar_url}')"></div>`
        : generateInitialAvatar(username, 32);
    div.innerHTML = `${avatarHtml}<span class="barrage-user">${username}：<span class="barrage-content">${content}</span>`;
    div.style.color = color;
    div.style.backgroundColor = hexToRgba(bg_color, 0.4);
    if (mode === 'center') {
        div.classList.add('static-center');
        div.style.top = Math.random() * (container.clientHeight - 44) + 'px';
        container.appendChild(div);
        setTimeout(() => { if (div.parentNode) div.remove(); }, 5000);
    } else {
        const lineHeight = 50;
        const lineIndex = getFreeLine(lineHeight);
        if (lineIndex === undefined) return;
        div.style.top = (lineIndex * lineHeight + Math.random() * (lineHeight - 44)) + 'px';
        div.style.visibility = 'hidden';
        container.appendChild(div);
        const width = div.clientWidth;
        const totalDistance = window.innerWidth + width;
        const duration = Math.max((totalDistance / speed), 5);
        div.classList.add(mode === 'right' ? 'move-right' : 'move-left');
        div.style.animationDuration = duration + 's';
        div.style.visibility = 'visible';
        updateLine(lineIndex, duration * 1000);
        div.addEventListener('animationend', () => {
            if (div.parentNode) div.remove();
            releaseLine(lineIndex);
        }, { once: true });
    }
}

function createGhostBarrage() {
    if (isInitialLoading || historicalBarrages.length === 0) return;
    const currentlyOnScreenIds = new Set(Array.from(container.querySelectorAll('.barrage')).map(b => parseInt(b.dataset.id, 10)));
    const availableChoices = historicalBarrages.filter(barrage => !currentlyOnScreenIds.has(barrage.id));
    if (availableChoices.length > 0) {
        const chosenBarrage = availableChoices[Math.floor(Math.random() * availableChoices.length)];
        createBarrage(chosenBarrage);
    }
}

// --- 弹幕轨道与工具函数 ---
function getFreeLine(lineHeight) {
    const lineCount = Math.floor((window.innerHeight - 80) / lineHeight);
    if (lines.length !== lineCount) lines = Array(lineCount).fill(0);
    const now = Date.now();
    const availableLines = [];
    for (let i = 0; i < lines.length; i++) { if (lines[i] <= now) availableLines.push(i); }
    if (availableLines.length > 0) { return availableLines[Math.floor(Math.random() * availableLines.length)]; }
    let soonestLineIndex = 0, minTime = lines[0];
    for (let i = 1; i < lines.length; i++) { if (lines[i] < minTime) { minTime = lines[i]; soonestLineIndex = i; } }
    return soonestLineIndex;
}
function updateLine(index, duration) { if (index >= 0 && index < lines.length) lines[index] = Date.now() + duration; }
function releaseLine(index) { if (index >= 0 && index < lines.length) lines[index] = 0; }
function generateInitialAvatar(username, size = 44) {
    const initial = username.charAt(0).toUpperCase();
    let hash = 0;
    for (let i = 0; i < username.length; i++) hash = username.charCodeAt(i) + ((hash << 5) - hash);
    const h = hash % 360;
    const color = `hsl(${h}, 50%, 45%)`;
    const fontSize = size * 0.5;
    const avatarClass = (size === 44) ? 'avatar' : 'barrage-avatar';
    return `<div class="${avatarClass} initial-avatar" style="background-color: ${color}; width:${size}px; height:${size}px; font-size:${fontSize}px; line-height:${size}px;">${initial}</div>`;
}
function hexToRgba(hex, opacity) {
    let c;
    if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
        c = hex.substring(1).split('');
        if (c.length == 3) c = [c[0], c[0], c[1], c[1], c[2], c[2]];
        c = '0x' + c.join('');
        return `rgba(${[(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',')},${opacity})`;
    }
    return 'rgba(0,0,0,0.4)';
}
async function submitReport(barrageId) {
    if (!currentUser.isLoggedIn) { showModal('loginModal'); return; }
    try {
        const formData = new FormData();
        formData.append('barrage_id', barrageId);
        const response = await fetch('/api/report_barrage.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);
    } catch (error) { console.error('举报时发生网络错误：', error); }
}

// --- 事件监听器绑定 ---
textInput.addEventListener('input', function() {
    updateCharCounter(this.value.length);
});
sendButton.addEventListener('click', sendBarrage);
hideBarragesCheckbox.addEventListener('change', function() { container.classList.toggle('hidden', this.checked); });
panelToggleButton.addEventListener('click', () => {
    controlPanel.classList.toggle('is-open');
    panelToggleButton.querySelector('i').classList.toggle('fa-chevron-up');
    panelToggleButton.querySelector('i').classList.toggle('fa-chevron-down');
});
document.getElementById('avatarUploadInput').addEventListener('change', async function(event) {
    const file = event.target.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('avatar', file);
    try {
        const response = await fetch('/api/upload_avatar.php', { method: 'POST', body: formData });
        const result = await response.json();
        alert(result.message);
        if (result.success) await checkLoginStatus();
    } catch (error) { console.error('上传头像时发生网络错误：', error); }
    event.target.value = '';
});
container.addEventListener('contextmenu', function(event) {
    const targetBarrage = event.target.closest('.barrage');
    if (targetBarrage) {
        event.preventDefault();
        if (currentBarrageElement && currentBarrageElement !== targetBarrage) {
            currentBarrageElement.classList.remove('paused');
        }
        currentBarrageElement = targetBarrage;
        currentBarrageElement.classList.add('paused');
        const contentEl = currentBarrageElement.querySelector('.barrage-content');
        if(contentEl) currentBarrageContent = contentEl.textContent;
        contextMenu.classList.remove('active');
        contextMenu.style.transition = 'none';
        contextMenu.style.top = `${event.clientY}px`;
        contextMenu.style.left = `${event.clientX}px`;
        void contextMenu.offsetHeight;
        contextMenu.style.transition = 'all 0.15s ease-out';
        contextMenu.classList.add('active');
    }
});
window.addEventListener('click', function() {
    if (contextMenu.classList.contains('active')) {
        contextMenu.classList.remove('active');
    }
    if (currentBarrageElement) {
        currentBarrageElement.classList.remove('paused');
        currentBarrageElement = null;
    }
});
reportMenuItem.addEventListener('click', function() { if (currentBarrageElement) submitReport(currentBarrageElement.dataset.id); });
copyMenuItem.addEventListener('click', function() { if (currentBarrageContent) { navigator.clipboard.writeText(currentBarrageContent).then(() => alert('弹幕内容已复制到剪贴板！')).catch(err => console.error('复制失败：', err)); } });
document.getElementById('registerForm').addEventListener('submit', async function(event) { event.preventDefault(); const form=this; const formData = new FormData(form); const response = await fetch('/api/register.php', { method: 'POST', body: formData }); const result = await response.json(); showMessage('registerMessage', result.message, result.success); if(result.success){ setTimeout(() => { hideModal('registerModal'); form.reset(); }, 2000); } });
document.getElementById('loginForm').addEventListener('submit', async function(event) { event.preventDefault(); const form=this; const formData = new FormData(form); const response = await fetch('/api/login.php', { method: 'POST', body: formData }); const result = await response.json(); showMessage('loginMessage', result.message, result.success); if (result.success) { currentUser = result.user; currentUser.isLoggedIn = true; updateUI(); setTimeout(() => { hideModal('loginModal'); form.reset(); }, 1000); } });
document.getElementById('changePasswordForm').addEventListener('submit', async function(event) { event.preventDefault(); const form=this; const newPassword = form.querySelector('#new-password').value; const confirmPassword = form.querySelector('#confirm-password').value; if (newPassword !== confirmPassword) { showMessage('changePasswordMessage', '两次输入的新密码不一致！', false); return; } const formData = new FormData(form); const response = await fetch('/api/change_password.php', { method: 'POST', body: formData }); const result = await response.json(); showMessage('changePasswordMessage', result.message, result.success); if (result.success) { setTimeout(() => { hideModal('changePasswordModal'); form.reset(); handleLogout(); }, 2000); } });
document.getElementById('requestResetForm').addEventListener('submit', async function(event) { event.preventDefault(); const formData = new FormData(this); const button = this.querySelector('button'); button.disabled = true; button.textContent = '正在发送...'; const response = await fetch('/api/request_password_reset.php', { method: 'POST', body: formData }); const result = await response.json(); showMessage('requestResetMessage', result.message, result.success); button.disabled = false; button.textContent = '发送重置链接'; });
document.getElementById('resetPasswordForm').addEventListener('submit', async function(event) { event.preventDefault(); const form=this; const newPassword = form.querySelector('#reset-new-password').value; const confirmPassword = form.querySelector('#reset-confirm-password').value; if (newPassword !== confirmPassword) { showMessage('resetPasswordMessage', '两次输入的新密码不一致！', false); return; } const formData = new FormData(form); const response = await fetch('/api/reset_password.php', { method: 'POST', body: formData }); const result = await response.json(); showMessage('resetPasswordMessage', result.message, result.success); if (result.success) { setTimeout(() => { hideModal('resetPasswordModal'); form.reset(); }, 2000); } });
