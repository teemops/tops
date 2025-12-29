import { Response } from 'express';

export interface ApiResponse<T = any> {
  success: boolean;
  data?: T;
  message?: string;
  error?: string;
  statusCode?: number;
}

export class ResponseUtil {
  static success<T>(data?: T, message?: string): ApiResponse<T> {
    return {
      success: true,
      data,
      message,
    };
  }

  static error(error: string, statusCode: number = 400): ApiResponse {
    return {
      success: false,
      error,
      statusCode,
    };
  }

  static sendSuccess<T>(res: Response, data?: T, message?: string, statusCode: number = 200) {
    return res.status(statusCode).json(this.success(data, message));
  }

  static sendError(res: Response, error: string, statusCode: number = 400) {
    return res.status(statusCode).json(this.error(error, statusCode));
  }
}

