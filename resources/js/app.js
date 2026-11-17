import Alpine from 'alpinejs';
import {
    createIcons, Scissors, Calendar, CalendarPlus, CalendarDays, CalendarCheck, CalendarClock, Clock,
    User, Users, UserX, Contact, Phone, MapPin, LayoutDashboard, ChartColumn, Settings, Search, Wallet, Lock,
    Check, CircleCheck, X, ChevronLeft, ChevronRight, Menu, LogOut, LoaderCircle,
    Plus, Pencil, Trash2, ImagePlus, Ban, TriangleAlert,
} from 'lucide';
import bookingWizard from './booking-wizard';

// Yalnız istifadə olunan ikonlar bundle-a düşür (tree-shaking). Yeni ikon lazım olanda buraya əlavə et.
const icons = {
    Scissors, Calendar, CalendarPlus, CalendarDays, CalendarCheck, CalendarClock, Clock,
    User, Users, UserX, Contact, Phone, MapPin, LayoutDashboard, ChartColumn, Settings, Search, Wallet, Lock,
    Check, CircleCheck, X, ChevronLeft, ChevronRight, Menu, LogOut, LoaderCircle,
    Plus, Pencil, Trash2, ImagePlus, Ban, TriangleAlert,
};

// Lucide: <i data-lucide="scissors"></i> elementlərini SVG ikonlara çevirir.
const renderIcons = () => createIcons({ icons });
window.renderIcons = renderIcons; // Alpine dinamik HTML əlavə edəndə yenidən çağırmaq üçün

// Alpine.js: Blade-dəki x-data / x-show / @click atributlarını canlandırır.
// İkonlar Alpine-in <template x-for> ilə yaratdığı elementləri də əhatə etsin deyə, Alpine-dən sonra çəkilir.
window.Alpine = Alpine;
Alpine.data('bookingWizard', bookingWizard);
Alpine.start();
document.addEventListener('DOMContentLoaded', renderIcons);
