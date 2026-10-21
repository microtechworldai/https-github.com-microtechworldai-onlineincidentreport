import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Shield, Lock, User, AlertCircle, ArrowRight, Loader2, Key } from 'lucide-react';
import { adminApi } from '../../lib/api';

export const AdminLogin: React.FC = () => {
  const navigate = useNavigate();
  const [username, setUsername] = useState<string>('');
  const [password, setPassword] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!username.trim() || !password) {
      setError('Please provide both username and password.');
      return;
    }

    setLoading(true);
    setError(null);

    try {
      const res = await adminApi.login({
        username: username.trim(),
        password,
      });

      if (res.admin.mustChangePassword) {
        navigate('/admin/change-password');
      } else {
        navigate('/admin');
      }
    } catch (err: any) {
      setError(err.message || 'Invalid administrator credentials');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-4">
      <div className="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100">
        {/* Brand Banner */}
        <div className="bg-[#0F2042] p-8 text-white text-center relative">
          <div className="w-16 h-16 mx-auto rounded-2xl bg-white p-2 flex items-center justify-center shadow-lg mb-3 overflow-hidden">
            <img
              src="/assets/uss-logo.png"
              alt="USS Logo"
              className="w-full h-full object-contain"
            />
          </div>
          <h2 className="text-xl font-extrabold tracking-wide uppercase">
            Ultimate Security Services
          </h2>
          <p className="text-xs text-amber-400 font-semibold tracking-widest uppercase mt-0.5">
            Super Administrator Portal
          </p>
          <div className="mt-3 inline-block px-3 py-1 bg-white/10 rounded-full text-xs font-semibold tracking-wide text-slate-200">
            Protected Management Realm
          </div>
        </div>

        {/* Login Form */}
        <div className="p-8">
          {error && (
            <div className="mb-5 p-3.5 bg-red-50 text-red-700 rounded-xl text-xs border border-red-200 flex items-start gap-2.5">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <span>{error}</span>
            </div>
          )}

          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Super Admin Username
              </label>
              <div className="relative">
                <input
                  type="text"
                  required
                  autoFocus
                  autoCapitalize="none"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="Enter administrator username"
                  className="w-full pl-10 pr-3 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                />
                <User className="w-5 h-5 text-slate-400 absolute left-3 top-3.5" />
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Admin Password
              </label>
              <div className="relative">
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter password"
                  className="w-full pl-10 pr-3 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                />
                <Lock className="w-5 h-5 text-slate-400 absolute left-3 top-3.5" />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3.5 bg-[#0F2042] hover:bg-[#162e5c] active:scale-[0.99] disabled:opacity-50 text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm mt-3"
            >
              {loading ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  <span>Authenticating...</span>
                </>
              ) : (
                <>
                  <span>Access Dashboard</span>
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </form>

          {/* Security Notice */}
          <div className="mt-8 pt-4 border-t border-slate-100 text-[11px] text-slate-500 text-center space-y-1">
            <p className="font-semibold text-slate-700">Strictly Authorized Personnel Only</p>
            <p>
              Forgot password? Use the server console command:{' '}
              <code className="bg-slate-100 px-1.5 py-0.5 rounded font-mono text-slate-800">
                npm run reset-admin-password
              </code>
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};
