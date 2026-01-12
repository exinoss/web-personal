
const Calculadora = {
    num1: '',
    num2: '',
    operacion: '',
    display: '0',
    esperandoNum2: false,
    
    init() {
        this.bindEvents();
        this.actualizar();
    },

    bindEvents() {
        document.querySelectorAll('.calc-keypad button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.procesar(btn.value);
            });
        });

        // Soporte teclado
        document.addEventListener('keydown', (e) => {
            const map = {
                '0':'0','1':'1','2':'2','3':'3','4':'4',
                '5':'5','6':'6','7':'7','8':'8','9':'9',
                '.':'.','+':'+','-':'-','*':'×','/':'÷',
                'Enter':'=','=':'=','Backspace':'⌫',
                'Escape':'C','Delete':'C'
            };
            if (map[e.key]) {
                e.preventDefault();
                this.procesar(map[e.key]);
            }
        });
    },

    procesar(tecla) {
        // Limpiar
        if (tecla === 'C') {
            this.limpiar();
            return;
        }

        // Borrar último caracter
        if (tecla === '⌫') {
            this.borrar();
            return;
        }

        // Es un número o punto
        if ('0123456789.'.includes(tecla)) {
            this.agregarNumero(tecla);
            return;
        }

        // Es una operación
        if ('+-×÷'.includes(tecla)) {
            this.setOperacion(tecla);
            return;
        }

        // Calcular
        if (tecla === '=') {
            this.calcular();
            return;
        }

        // Porcentaje
        if (tecla === '%') {
            this.porcentaje();
            return;
        }

        // Cambiar signo
        if (tecla === '±') {
            this.cambiarSigno();
            return;
        }
    },

    limpiar() {
        this.num1 = '';
        this.num2 = '';
        this.operacion = '';
        this.display = '0';
        this.esperandoNum2 = false;
        this.actualizar();
    },

    borrar() {
        if (this.esperandoNum2) {
            this.num2 = this.num2.slice(0, -1);
            this.display = this.num2 || '0';
        } else {
            this.num1 = this.num1.slice(0, -1);
            this.display = this.num1 || '0';
        }
        this.actualizar();
    },

    agregarNumero(n) {
        // Evitar múltiples puntos
        if (n === '.') {
            if (this.esperandoNum2 && this.num2.includes('.')) return;
            if (!this.esperandoNum2 && this.num1.includes('.')) return;
        }

        if (this.esperandoNum2) {
            this.num2 += n;
            this.display = this.num2;
        } else {
            if (this.num1 === '0' && n !== '.') {
                this.num1 = n;
            } else {
                this.num1 += n;
            }
            this.display = this.num1;
        }
        this.actualizar();
    },

    setOperacion(op) {
        if (this.num1 === '') return;
        
        // Si ya hay operación pendiente, calcular primero
        if (this.operacion && this.num2 !== '') {
            this.calcular();
        }
        
        this.operacion = op;
        this.esperandoNum2 = true;
        this.actualizar();
    },

    cambiarSigno() {
        if (this.esperandoNum2) {
            if (this.num2.startsWith('-')) {
                this.num2 = this.num2.slice(1);
            } else {
                this.num2 = '-' + this.num2;
            }
            this.display = this.num2;
        } else {
            if (this.num1.startsWith('-')) {
                this.num1 = this.num1.slice(1);
            } else if (this.num1 !== '') {
                this.num1 = '-' + this.num1;
            }
            this.display = this.num1 || '0';
        }
        this.actualizar();
    },

    porcentaje() {
        if (this.esperandoNum2 && this.num2 !== '') {
            this.num2 = String(parseFloat(this.num2) / 100);
            this.display = this.num2;
        } else if (this.num1 !== '') {
            this.num1 = String(parseFloat(this.num1) / 100);
            this.display = this.num1;
        }
        this.actualizar();
    },

    async calcular() {
        if (this.num1 === '' || this.operacion === '' || this.num2 === '') {
            return;
        }

        // Convertir símbolo a operador
        const ops = {'×': '*', '÷': '/', '+': '+', '-': '-'};
        const opPHP = ops[this.operacion];

        try {
            const res = await fetch('./operaciones.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    num1: parseFloat(this.num1),
                    num2: parseFloat(this.num2),
                    operacion: opPHP
                })
            });
            
            const data = await res.json();
            
            if (data.ok) {
                this.num1 = String(data.resultado);
                this.num2 = '';
                this.operacion = '';
                this.esperandoNum2 = false;
                this.display = this.num1;
            } else {
                this.mostrarError(data.error);
            }
        } catch (e) {
            this.mostrarError('Error de conexión');
        }

        this.actualizar();
    },

    actualizar() {
        const hist = document.querySelector('.calc-history');
        const curr = document.querySelector('.calc-display');
        
        // Mostrar expresión en el historial
        let expresion = '';
        if (this.num1) expresion = this.num1;
        if (this.operacion) expresion += ' ' + this.operacion;
        if (this.num2) expresion += ' ' + this.num2;
        
        if (hist) hist.textContent = expresion;
        if (curr) curr.textContent = this.display;
    },

    mostrarError(msg) {
        const alert = document.querySelector('.calc-error');
        if (alert) {
            alert.textContent = msg;
            alert.style.display = 'block';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 3000);
        }
    }
};

document.addEventListener('DOMContentLoaded', () => Calculadora.init());
