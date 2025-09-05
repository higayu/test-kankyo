import axios from 'axios'
import type { AxiosInstance, AxiosError, AxiosRequestConfig, AxiosResponse } from 'axios'

interface ApiError {
  type: 'connection_error' | 'auth_error' | 'unknown_error';
  message: string;
}

class ApiService {
  private api: AxiosInstance;

  constructor() {
    this.api = axios.create({
      baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8001/api/',
      headers: {
        'Accept': 'application/json',
      },
    });

    // リクエストインターセプター
    this.api.interceptors.request.use(
      (config) => {
        // リクエストの詳細をログ出力
        console.log('=== APIリクエスト開始 ===', {
          timestamp: new Date().toISOString(),
          url: config.url,
          method: config.method,
          baseURL: config.baseURL,
          headers: config.headers,
          data: config.data instanceof FormData ? {
            isFormData: true,
            entries: Array.from(config.data.entries()).map(([key, value]) => ({
              key,
              value: value instanceof File ? {
                name: value.name,
                size: value.size,
                type: value.type,
                lastModified: value.lastModified
              } : value
            }))
          } : config.data
        });

        // 必要に応じて認証トークンを追加
        const token = localStorage.getItem('token');
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
          console.log('認証トークン追加:', { token: token.substring(0, 10) + '...' });
        }

        // FormDataの場合はContent-Typeを自動設定
        if (config.data instanceof FormData) {
          delete config.headers['Content-Type'];
          console.log('FormData検出: Content-Typeヘッダーを自動設定に任せる');
        }

        return config;
      },
      (error) => {
        console.error('リクエストインターセプターエラー:', error);
        return Promise.reject(error);
      }
    );

    // レスポンスインターセプター
    this.api.interceptors.response.use(
      (response) => {
        console.log('=== APIレスポンス受信 ===', {
          timestamp: new Date().toISOString(),
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
          data: response.data,
          config: {
            url: response.config.url,
            method: response.config.method,
            headers: response.config.headers
          }
        });
        return response;
      },
      (error) => {
        console.error('=== APIレスポンスエラー ===', {
          timestamp: new Date().toISOString(),
          error: {
            message: error.message,
            code: error.code,
            status: error.response?.status,
            statusText: error.response?.statusText,
            data: error.response?.data,
            headers: error.response?.headers,
            config: {
              url: error.config?.url,
              method: error.config?.method,
              headers: error.config?.headers,
              data: error.config?.data instanceof FormData ? 'FormData' : error.config?.data
            }
          }
        });
        return Promise.reject(error);
      }
    );
  }

  // エラーハンドリング
  private handleError(error: unknown): never {
    if (axios.isAxiosError(error)) {
      const axiosError = error as AxiosError<{ message?: string; error_details?: any }>;
      const message = axiosError.response?.data?.message || axiosError.message || 'APIリクエストに失敗しました';
      const errorDetails = axiosError.response?.data?.error_details;
      
      console.error('APIエラー詳細:', {
        status: axiosError.response?.status,
        statusText: axiosError.response?.statusText,
        data: axiosError.response?.data,
        headers: axiosError.response?.headers,
        errorDetails
      });
      
      throw new Error(message);
    }
    
    if (error instanceof Error) {
      throw error;
    }
    
    throw new Error('予期せぬエラーが発生しました');
  }

  // 音声ファイルのノイズ除去
  async denoiseAudio(audioData: File | FormData): Promise<{
    success: boolean;
    message: string;
    data: {
      file: {
        path: string;
        original_name: string;
        size: number;
        mime_type: string;
      }
    };
  }> {
    try {
      let formData: FormData;
      let audioFile: File;

      if (audioData instanceof FormData) {
        formData = audioData;
        const file = formData.get('audio');
        if (!(file instanceof File)) {
          throw new Error('FormDataにファイルが正しく追加されていません');
        }
        audioFile = file;
      } else {
        audioFile = audioData;
        formData = new FormData();
        
        // ファイルの内容を確認
        if (audioFile.size === 0) {
          throw new Error('ファイルが空です');
        }

        // ファイル名を確認
        if (!audioFile.name) {
          throw new Error('ファイル名が設定されていません');
        }

        // ファイルの種類を確認
        if (!audioFile.type.startsWith('audio/')) {
          throw new Error('無効なファイル形式です。音声ファイルを選択してください。');
        }

        // FormDataにファイルを追加（キー名を'audio'に固定）
        formData.append('audio', audioFile);

        // FormDataの内容を確認
        const formDataFile = formData.get('audio');
        if (!(formDataFile instanceof File)) {
          throw new Error('FormDataにファイルが正しく追加されていません');
        }

        console.log('FormDataに追加されたファイルの確認:', {
          timestamp: new Date().toISOString(),
          file: {
            name: formDataFile.name,
            size: formDataFile.size,
            type: formDataFile.type,
            lastModified: formDataFile.lastModified
          },
          formDataEntries: Array.from(formData.entries()).map(([key, value]) => ({
            key,
            value: value instanceof File ? {
              name: value.name,
              size: value.size,
              type: value.type
            } : value
          }))
        });
      }

      // リクエストの設定
      const config: AxiosRequestConfig = {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        // Content-Typeは自動設定に任せる
        transformRequest: [(data) => {
          if (data instanceof FormData) {
            // FormDataの内容を確認
            const file = data.get('audio');
            if (!(file instanceof File)) {
              throw new Error('FormDataにファイルが正しく追加されていません');
            }
            // ファイルの存在確認
            if (file.size === 0) {
              throw new Error('ファイルが空です');
            }
            return data;
          }
          return data;
        }],
        onUploadProgress: (progressEvent: any) => {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          console.log('アップロード進捗:', {
            timestamp: new Date().toISOString(),
            loaded: progressEvent.loaded,
            total: progressEvent.total,
            percent: percentCompleted,
            file: formData.get('audio') instanceof File ? {
              name: (formData.get('audio') as File).name,
              size: (formData.get('audio') as File).size,
              type: (formData.get('audio') as File).type
            } : 'Not a File'
          });
        },
        timeout: 300000, // 5分
        maxContentLength: 25 * 1024 * 1024, // 25MB
        maxBodyLength: 25 * 1024 * 1024 // 25MB
      };

      // APIリクエストの送信
      console.log('APIリクエスト送信開始:', {
        timestamp: new Date().toISOString(),
        url: 'audio/denoise',
        method: 'POST',
        headers: config.headers,
        formData: {
          hasAudio: formData.has('audio'),
          file: formData.get('audio') instanceof File ? {
            name: (formData.get('audio') as File).name,
            size: (formData.get('audio') as File).size,
            type: (formData.get('audio') as File).type
          } : 'Not a File',
          entries: Array.from(formData.entries()).map(([key, value]) => ({
            key,
            value: value instanceof File ? {
              name: value.name,
              size: value.size,
              type: value.type
            } : value
          }))
        }
      });

      const response = await this.api.post('audio/denoise', formData, config);

      console.log('=== 音声ファイルアップロード完了 ===', {
        timestamp: new Date().toISOString(),
        response: {
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
          data: response.data
        }
      });

      return response.data;
    } catch (error) {
      console.error('=== 音声ファイルアップロードエラー ===', {
        timestamp: new Date().toISOString(),
        error: error instanceof Error ? {
          name: error.name,
          message: error.message,
          stack: error.stack
        } : error,
        requestInfo: {
          url: 'audio/denoise',
          method: 'POST',
          formData: audioData instanceof FormData ? {
            hasAudio: audioData.has('audio'),
            file: audioData.get('audio') instanceof File ? {
              name: (audioData.get('audio') as File).name,
              size: (audioData.get('audio') as File).size,
              type: (audioData.get('audio') as File).type
            } : 'Not a File',
            entries: Array.from(audioData.entries()).map(([key, value]) => ({
              key,
              value: value instanceof File ? {
                name: value.name,
                size: value.size,
                type: value.type
              } : value
            }))
          } : 'Not FormData'
        }
      });
      this.handleError(error);
    }
  }

  // 音声ファイルのアップロード
  async uploadAudio(audioBlob: Blob, filename: string): Promise<{
    success: boolean;
    message: string;
    data: {
      file: {
        path: string;
        original_name: string;
        size: number;
        mime_type: string;
      }
    };
  }> {
    try {
      // BlobをFileオブジェクトに変換
      const audioFile = new File([audioBlob], `${filename}.wav`, {
        type: 'audio/wav'
      });

      // FormDataの作成
      const formData = new FormData();
      formData.append('audio', audioFile);
      formData.append('filename', filename);

      // リクエストの設定
      const config: AxiosRequestConfig = {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        onUploadProgress: (progressEvent: any) => {
          const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
          console.log('音声ファイルアップロード進捗:', {
            timestamp: new Date().toISOString(),
            loaded: progressEvent.loaded,
            total: progressEvent.total,
            percent: percentCompleted,
            filename: filename
          });
        },
        timeout: 300000, // 5分
        maxContentLength: 25 * 1024 * 1024, // 25MB
        maxBodyLength: 25 * 1024 * 1024 // 25MB
      };

      // APIリクエストの送信
      const response = await this.api.post('audio/test-save', formData, config);

      console.log('=== 音声ファイルアップロード完了 ===', {
        timestamp: new Date().toISOString(),
        filename: filename,
        response: response
      });

      return response.data;
    } catch (error) {
      console.error('=== 音声ファイルアップロードエラー ===', {
        timestamp: new Date().toISOString(),
        filename: filename,
        error: error instanceof Error ? {
          name: error.name,
          message: error.message,
          stack: error.stack
        } : error
      });
      this.handleError(error);
    }
  }

  // 認証トークンの設定
  setAuthToken(token: string): void {
    localStorage.setItem('token', token);
    this.api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  }

  // 認証トークンのクリア
  clearAuthToken(): void {
    localStorage.removeItem('token');
    delete this.api.defaults.headers.common['Authorization'];
  }
}

// シングルトンインスタンスをエクスポート
const apiService = new ApiService();
export default apiService; 